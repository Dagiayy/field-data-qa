import React, { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { listQuestsApi, getQuestBaselineApi, updateQuestBaselineApi } from '../api/quests';
import { createBaselineApi, updateBaselineApi } from '../api/outlets';
import type { QuestListItem, QuestBaselineOutletSpot } from '../types';
import { useToast } from './ToastProvider';
import {
  Search, Plus, Trash2, Clock, DollarSign, AlertTriangle, CheckCircle2, MapPin,
  Edit2, Upload, Navigation, AlertCircle, ImageOff,
} from 'lucide-react';

interface PriceRangeRow {
  sku_id: string;
  min: string;
  max: string;
  currency: string;
  unit: string;
}

const emptyRow = (): PriceRangeRow => ({ sku_id: '', min: '', max: '', currency: 'ETB', unit: '' });

const formatDuration = (seconds: number | null): string => {
  if (seconds === null) return '—';
  return `${Math.floor(seconds / 60)}m`;
};

/**
 * Baseline Management, quest-first: pick one quest and every baseline type
 * it needs — the GPS/reference-photo spot (physically the quest's one
 * outlet, via Quest::outlet_id), the expected survey duration range, and
 * the expected price range per SKU — is fillable from this one screen. No
 * separate outlet-picking step, no separate tab.
 */
export const QuestBaselinePanel: React.FC = () => {
  const [search, setSearch] = useState('');
  const [selectedQuest, setSelectedQuest] = useState<QuestListItem | null>(null);
  const [minMinutes, setMinMinutes] = useState('');
  const [maxMinutes, setMaxMinutes] = useState('');
  const [priceRows, setPriceRows] = useState<PriceRangeRow[]>([]);

  // Photo/GPS spot form state
  const [isSpotFormOpen, setIsSpotFormOpen] = useState(false);
  const [editingSpot, setEditingSpot] = useState<QuestBaselineOutletSpot | null>(null);
  const [spotLabel, setSpotLabel] = useState('Shelf Aisle 1');
  const [gpsLat, setGpsLat] = useState('');
  const [gpsLng, setGpsLng] = useState('');
  const [radiusM, setRadiusM] = useState('75');
  const [spotNotes, setSpotNotes] = useState('');
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [geoError, setGeoError] = useState<string | null>(null);

  const queryClient = useQueryClient();
  const { showToast } = useToast();

  const { data: questsResponse, isLoading: isLoadingQuests } = useQuery({
    queryKey: ['questList'],
    queryFn: listQuestsApi,
  });

  const { data: baseline, isLoading: isLoadingBaseline } = useQuery({
    queryKey: ['questBaseline', selectedQuest?.form_code],
    queryFn: () => getQuestBaselineApi(selectedQuest!.form_code),
    enabled: !!selectedQuest,
  });

  const outlet = baseline?.outlet ?? null;

  // Seed the editable duration/price fields whenever a different quest's
  // baseline finishes loading.
  useEffect(() => {
    if (!baseline) return;
    setMinMinutes(baseline.expected_duration_min_seconds != null ? String(Math.round(baseline.expected_duration_min_seconds / 60)) : '');
    setMaxMinutes(baseline.expected_duration_max_seconds != null ? String(Math.round(baseline.expected_duration_max_seconds / 60)) : '');
    setPriceRows(
      baseline.price_ranges.length > 0
        ? baseline.price_ranges.map((r) => ({
            sku_id: r.sku_id,
            min: String(r.min),
            max: String(r.max),
            currency: r.currency,
            unit: r.unit || '',
          }))
        : []
    );
    setIsSpotFormOpen(false);
    setEditingSpot(null);
  }, [baseline]);

  // Saving a baseline triggers QaReevaluationService server-side, which
  // re-scores every queued submission for this outlet/quest — so every page
  // whose data depends on QA flags/trust scores/status needs to refetch,
  // not just this panel's own queries.
  const invalidateReevaluatedQueues = () => {
    queryClient.invalidateQueries({ queryKey: ['qaQueue'] });
    queryClient.invalidateQueries({ queryKey: ['backcheckQueue'] });
    queryClient.invalidateQueries({ queryKey: ['paymentStatusQueue'] });
    queryClient.invalidateQueries({ queryKey: ['submissionDetail'] });
    queryClient.invalidateQueries({ queryKey: ['agentTrustScore'] });
  };

  const saveDurationPriceMutation = useMutation({
    mutationFn: () => {
      const validRows = priceRows.filter((r) => r.sku_id.trim() !== '' && r.min !== '' && r.max !== '');
      return updateQuestBaselineApi(selectedQuest!.form_code, {
        expected_duration_min_seconds: minMinutes !== '' ? Math.round(parseFloat(minMinutes) * 60) : null,
        expected_duration_max_seconds: maxMinutes !== '' ? Math.round(parseFloat(maxMinutes) * 60) : null,
        price_ranges: validRows.map((r) => ({
          sku_id: r.sku_id.trim(),
          min: parseFloat(r.min),
          max: parseFloat(r.max),
          currency: r.currency.trim() || 'ETB',
          unit: r.unit.trim() || undefined,
        })),
      });
    },
    onSuccess: () => {
      showToast('Baseline Saved', 'Duration & price baseline updated — it now applies to every future submission for this quest.', 'success');
      queryClient.invalidateQueries({ queryKey: ['questBaseline', selectedQuest?.form_code] });
      queryClient.invalidateQueries({ queryKey: ['questList'] });
      invalidateReevaluatedQueues();
    },
    onError: (err: any) => {
      showToast('Save Failed', err.response?.data?.message || 'Failed to save quest baseline.', 'error');
    },
  });

  const createSpotMutation = useMutation({
    mutationFn: (formData: FormData) => createBaselineApi(outlet!.id, formData),
    onSuccess: () => {
      showToast('Baseline Photo Saved', 'New spot baseline registered for this quest\'s outlet.', 'success');
      queryClient.invalidateQueries({ queryKey: ['questBaseline', selectedQuest?.form_code] });
      invalidateReevaluatedQueues();
      resetSpotForm();
    },
    onError: (err: any) => {
      showToast('Save Failed', err.response?.data?.message || 'Failed to create baseline photo.', 'error');
    },
  });

  const updateSpotMutation = useMutation({
    mutationFn: (formData: FormData) => updateBaselineApi(outlet!.id, editingSpot!.id, formData),
    onSuccess: () => {
      showToast('Baseline Photo Saved', 'Spot baseline updated.', 'success');
      queryClient.invalidateQueries({ queryKey: ['questBaseline', selectedQuest?.form_code] });
      invalidateReevaluatedQueues();
      resetSpotForm();
    },
    onError: (err: any) => {
      showToast('Save Failed', err.response?.data?.message || 'Failed to update baseline photo.', 'error');
    },
  });

  const handleUseCurrentLocation = () => {
    setGeoError(null);
    if (!navigator.geolocation) {
      setGeoError('Geolocation API is not supported by your browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGpsLat(pos.coords.latitude.toFixed(6));
        setGpsLng(pos.coords.longitude.toFixed(6));
        showToast('GPS Captured', `Acquired current location: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}`, 'info');
      },
      (err) => setGeoError(`Location access denied or unavailable: ${err.message}`)
    );
  };

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setPhotoFile(file);
      setPhotoPreview(URL.createObjectURL(file));
    }
  };

  const openCreateSpotForm = () => {
    setEditingSpot(null);
    setSpotLabel('Shelf Aisle 1');
    setGpsLat(outlet ? String(outlet.gps_lat) : '');
    setGpsLng(outlet ? String(outlet.gps_lng) : '');
    setRadiusM('75');
    setSpotNotes('');
    setPhotoFile(null);
    setPhotoPreview(null);
    setGeoError(null);
    setIsSpotFormOpen(true);
  };

  const openEditSpotForm = (spot: QuestBaselineOutletSpot) => {
    setEditingSpot(spot);
    setSpotLabel(spot.spot_label);
    setGpsLat(String(spot.baseline_gps_lat));
    setGpsLng(String(spot.baseline_gps_lng));
    setRadiusM(String(spot.baseline_gps_radius_m));
    setSpotNotes(spot.notes || '');
    setPhotoFile(null);
    setPhotoPreview(spot.photo_url);
    setGeoError(null);
    setIsSpotFormOpen(true);
  };

  const resetSpotForm = () => {
    setIsSpotFormOpen(false);
    setEditingSpot(null);
    setPhotoFile(null);
    setPhotoPreview(null);
  };

  const handleSpotSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!outlet) return;

    const fd = new FormData();
    fd.append('spot_label', spotLabel);
    fd.append('baseline_gps_lat', gpsLat);
    fd.append('baseline_gps_lng', gpsLng);
    fd.append('baseline_gps_radius_m', radiusM);
    if (spotNotes) fd.append('notes', spotNotes);
    if (photoFile) fd.append('photo', photoFile);

    if (editingSpot) {
      updateSpotMutation.mutate(fd);
    } else {
      createSpotMutation.mutate(fd);
    }
  };

  const filteredQuests = (questsResponse?.data || []).filter(
    (q) => !search || q.title.toLowerCase().includes(search.toLowerCase()) || q.form_code.toLowerCase().includes(search.toLowerCase())
  );

  const hasAnyPriceBaseline = (baseline?.price_ranges.length ?? 0) > 0;
  const hasAnyPhotoBaseline = (outlet?.baselines.length ?? 0) > 0;
  const hasNoBaselineAtAll = !!baseline && !baseline.has_duration_baseline && !hasAnyPriceBaseline && !hasAnyPhotoBaseline;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="bg-white rounded-2xl border border-gray-150 p-5 shadow-2xs space-y-4">
        <div className="relative">
          <Search className="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
          <input
            type="text"
            placeholder="Search quests by title or code..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-[#2E7D4F] focus:outline-none"
          />
        </div>

        <div className="space-y-2">
          <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Quests</h3>

          {isLoadingQuests ? (
            <div className="space-y-2">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="h-14 bg-gray-100 rounded-xl animate-pulse" />
              ))}
            </div>
          ) : (
            <div className="space-y-2 max-h-[600px] overflow-y-auto pr-1">
              {filteredQuests.map((quest) => (
                <div
                  key={quest.id}
                  onClick={() => setSelectedQuest(quest)}
                  className={`p-3 rounded-xl border transition-all cursor-pointer ${
                    selectedQuest?.id === quest.id
                      ? 'bg-[#EAF5EC] border-[#2E7D4F] shadow-xs'
                      : 'bg-white border-gray-150 hover:bg-gray-50'
                  }`}
                >
                  <div className="font-bold text-xs text-gray-900">{quest.title}</div>
                  <div className="text-[11px] text-gray-500 mt-0.5">{quest.outlet_name || 'No outlet linked'}</div>
                  <div className="text-[11px] text-gray-500 flex items-center justify-between mt-0.5">
                    <span className="font-mono">{quest.form_code}</span>
                    {quest.has_duration_baseline ? (
                      <span className="text-emerald-700 font-semibold flex items-center gap-0.5">
                        <CheckCircle2 className="w-3 h-3" /> Baselined
                      </span>
                    ) : (
                      <span className="text-amber-700 font-semibold flex items-center gap-0.5">
                        <AlertTriangle className="w-3 h-3" /> No baseline
                      </span>
                    )}
                  </div>
                </div>
              ))}
              {filteredQuests.length === 0 && (
                <p className="text-xs text-gray-400 italic p-2">No quests match this search.</p>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="lg:col-span-2 space-y-6">
        {!selectedQuest ? (
          <div className="bg-white rounded-2xl border border-dashed border-gray-200 p-12 text-center text-xs text-gray-500 space-y-2">
            <Clock className="w-8 h-8 text-gray-300 mx-auto" />
            <p className="font-semibold text-gray-700">Select a quest from the left list to view and manage every baseline it needs.</p>
          </div>
        ) : isLoadingBaseline ? (
          <div className="h-64 bg-white rounded-2xl border border-gray-150 animate-pulse" />
        ) : (
          <div className="space-y-6">
            <div className="bg-white rounded-2xl border border-gray-150 p-5 shadow-2xs">
              <h3 className="text-lg font-bold text-gray-900">{selectedQuest.title}</h3>
              <p className="text-xs text-gray-500 font-mono">{selectedQuest.form_code}</p>
            </div>

            {hasNoBaselineAtAll && (
              <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] flex items-start gap-2">
                <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                <span>This is a new quest with no baseline configured yet — until you set a photo spot, an expected duration, and/or a price range below, submissions to it will be flagged as "needs baseline setup" rather than fully validated.</span>
              </div>
            )}

            {/* Photo & GPS baseline */}
            <div className="bg-white rounded-2xl border border-gray-150 p-5 shadow-2xs space-y-4">
              <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                <h4 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                  <MapPin className="w-4 h-4 text-[#2E7D4F]" /> Baseline Photo &amp; GPS
                </h4>
                {outlet && (
                  <button
                    type="button"
                    onClick={openCreateSpotForm}
                    className="flex items-center gap-1 text-[11px] font-bold text-[#2E7D4F] hover:underline"
                  >
                    <Plus className="w-3.5 h-3.5" /> Add Spot
                  </button>
                )}
              </div>

              {!outlet ? (
                <p className="text-xs text-gray-400 italic py-2">
                  This quest has no outlet linked yet, so a GPS/photo spot can't be registered for it.
                </p>
              ) : (
                <>
                  <p className="text-[11px] text-gray-500">
                    Outlet: <span className="font-semibold text-gray-700">{outlet.name}</span> ({outlet.id})
                  </p>

                  {isSpotFormOpen && (
                    <form onSubmit={handleSpotSubmit} className="space-y-4 border border-emerald-200 rounded-xl p-4 bg-emerald-50/30">
                      <div className="flex items-center justify-between">
                        <h5 className="text-xs font-bold text-gray-900">{editingSpot ? 'Edit Spot Baseline' : 'Register New Spot Baseline'}</h5>
                        <button type="button" onClick={resetSpotForm} className="text-[11px] text-gray-400 hover:text-gray-600 font-semibold">
                          Cancel
                        </button>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1">
                          <label className="block text-xs font-semibold text-gray-700">Spot Label / Category</label>
                          <select
                            value={spotLabel}
                            onChange={(e) => setSpotLabel(e.target.value)}
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                          >
                            <option value="shelf">Shelf</option>
                            <option value="price_tag">Price Tag / Close-up</option>
                            <option value="Entrance Promo Display">Entrance Promo Display</option>
                            <option value="Chilled Cooler Unit">Chilled Cooler Unit</option>
                            <option value="Checkout Counter Facing">Checkout Counter Facing</option>
                            <option value="Other Spot">Other Spot</option>
                          </select>
                        </div>
                        <div className="space-y-1">
                          <label className="block text-xs font-semibold text-gray-700">Baseline GPS Radius (meters)</label>
                          <input
                            type="number"
                            value={radiusM}
                            onChange={(e) => setRadiusM(e.target.value)}
                            required
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                          />
                        </div>
                      </div>

                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <label className="block text-xs font-semibold text-gray-700">Baseline GPS Coordinates</label>
                          <button
                            type="button"
                            onClick={handleUseCurrentLocation}
                            className="flex items-center gap-1 text-[11px] font-bold text-[#2E7D4F] hover:underline"
                          >
                            <Navigation className="w-3 h-3" /> Use My Current Location
                          </button>
                        </div>

                        {geoError && (
                          <div className="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-[11px] flex items-center gap-1.5">
                            <AlertCircle className="w-3.5 h-3.5 shrink-0 text-rose-600" />
                            <span>{geoError}</span>
                          </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                          <input
                            type="text"
                            placeholder="Latitude"
                            value={gpsLat}
                            onChange={(e) => setGpsLat(e.target.value)}
                            required
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                          />
                          <input
                            type="text"
                            placeholder="Longitude"
                            value={gpsLng}
                            onChange={(e) => setGpsLng(e.target.value)}
                            required
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                          />
                        </div>
                      </div>

                      <div className="space-y-2">
                        <label className="block text-xs font-semibold text-gray-700">Spot Reference Photo</label>
                        <div className="flex items-center gap-4">
                          <label className="flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-300 rounded-xl cursor-pointer text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                            <Upload className="w-4 h-4 text-gray-500" /> Choose Photo File
                            <input type="file" accept="image/*" onChange={handlePhotoChange} className="hidden" />
                          </label>
                          {photoFile && <span className="text-xs font-mono text-gray-600">{photoFile.name}</span>}
                        </div>
                        {photoPreview && (
                          <div className="relative w-40 h-28 rounded-xl overflow-hidden border border-gray-200 mt-2">
                            <img src={photoPreview} alt="Baseline preview" className="w-full h-full object-cover" />
                          </div>
                        )}
                      </div>

                      <div className="space-y-1">
                        <label className="block text-xs font-semibold text-gray-700">Notes &amp; Instructions</label>
                        <textarea
                          rows={2}
                          value={spotNotes}
                          onChange={(e) => setSpotNotes(e.target.value)}
                          placeholder="Context or physical placement details..."
                          className="w-full rounded-xl border border-gray-300 bg-white p-2.5 text-xs text-gray-800"
                        />
                      </div>

                      <div className="flex justify-end gap-3 pt-1">
                        <button type="button" onClick={resetSpotForm} className="px-4 py-2 text-xs font-semibold text-gray-600 bg-gray-100 rounded-xl">
                          Cancel
                        </button>
                        <button
                          type="submit"
                          disabled={createSpotMutation.isPending || updateSpotMutation.isPending}
                          className="px-5 py-2 text-xs font-bold text-white bg-[#2E7D4F] hover:bg-[#24653F] rounded-xl shadow-2xs disabled:opacity-50"
                        >
                          Save Baseline Spot
                        </button>
                      </div>
                    </form>
                  )}

                  {outlet.baselines.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-200 p-6 text-center text-xs text-gray-500">
                      No spot baselines registered yet. Click "Add Spot" above.
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {outlet.baselines.map((spot) => (
                        <div key={spot.id} className="rounded-xl border border-gray-150 p-3 space-y-2">
                          <div className="relative aspect-16/9 rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                            {spot.photo_url ? (
                              <img src={spot.photo_url} alt={spot.spot_label} className="w-full h-full object-cover" />
                            ) : (
                              <div className="flex items-center justify-center w-full h-full text-gray-400">
                                <ImageOff className="w-6 h-6" />
                              </div>
                            )}
                            <div className="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-900/80 text-white text-[10px] font-bold capitalize">
                              {spot.spot_label.replace(/_/g, ' ')}
                            </div>
                          </div>
                          <div className="flex items-center justify-between text-[11px]">
                            <span className="text-gray-600">Lat {spot.baseline_gps_lat}, Lng {spot.baseline_gps_lng}</span>
                            <span className="font-bold text-[#2E7D4F] bg-[#EAF5EC] px-1.5 py-0.5 rounded">{spot.baseline_gps_radius_m}m</span>
                          </div>
                          {spot.notes && <p className="text-[11px] text-gray-500 italic line-clamp-2">{spot.notes}</p>}
                          <button
                            onClick={() => openEditSpotForm(spot)}
                            className="w-full py-1.5 border border-gray-200 rounded-lg text-[11px] font-semibold text-gray-700 hover:bg-gray-50 flex items-center justify-center gap-1.5 transition-colors"
                          >
                            <Edit2 className="w-3 h-3 text-gray-500" /> Edit
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>

            {/* Expected duration */}
            <div className="bg-white rounded-2xl border border-gray-150 p-5 shadow-2xs space-y-3">
              <h4 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                <Clock className="w-4 h-4 text-[#2E7D4F]" /> Expected Survey Duration
              </h4>
              <p className="text-[11px] text-gray-500">
                How long a legitimate survey for this quest should take, start to finish (minutes). Leave a field blank
                to not enforce that bound.
              </p>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="block text-xs font-semibold text-gray-700">Minimum (minutes)</label>
                  <input
                    type="number"
                    min={0}
                    value={minMinutes}
                    onChange={(e) => setMinMinutes(e.target.value)}
                    placeholder="e.g. 2"
                    className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                  />
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-semibold text-gray-700">Maximum (minutes)</label>
                  <input
                    type="number"
                    min={0}
                    value={maxMinutes}
                    onChange={(e) => setMaxMinutes(e.target.value)}
                    placeholder="e.g. 45"
                    className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800"
                  />
                </div>
              </div>
              {baseline?.has_duration_baseline && (
                <p className="text-[11px] text-gray-400">
                  Currently: {formatDuration(baseline.expected_duration_min_seconds)} – {formatDuration(baseline.expected_duration_max_seconds)}
                </p>
              )}
            </div>

            {/* Expected price ranges */}
            <div className="bg-white rounded-2xl border border-gray-150 p-5 shadow-2xs space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                  <DollarSign className="w-4 h-4 text-[#2E7D4F]" /> Expected Price Ranges
                </h4>
                <button
                  type="button"
                  onClick={() => setPriceRows((rows) => [...rows, emptyRow()])}
                  className="flex items-center gap-1 text-[11px] font-bold text-[#2E7D4F] hover:underline"
                >
                  <Plus className="w-3.5 h-3.5" /> Add SKU
                </button>
              </div>
              <p className="text-[11px] text-gray-500">
                Only used when a submission's own payload doesn't already carry a market_range_price for that price —
                the payload-supplied range always wins when present.
              </p>

              {priceRows.length === 0 ? (
                <p className="text-xs text-gray-400 italic py-2">
                  No price ranges configured — this quest either has no priced items, or expects each submission to
                  carry its own range.
                </p>
              ) : (
                <div className="space-y-2">
                  {priceRows.map((row, idx) => (
                    <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                      <input
                        placeholder="SKU ID"
                        value={row.sku_id}
                        onChange={(e) =>
                          setPriceRows((rows) => rows.map((r, i) => (i === idx ? { ...r, sku_id: e.target.value } : r)))
                        }
                        className="col-span-4 rounded-lg border border-gray-300 px-2 py-1.5 text-xs font-mono"
                      />
                      <input
                        placeholder="Min"
                        type="number"
                        value={row.min}
                        onChange={(e) =>
                          setPriceRows((rows) => rows.map((r, i) => (i === idx ? { ...r, min: e.target.value } : r)))
                        }
                        className="col-span-2 rounded-lg border border-gray-300 px-2 py-1.5 text-xs"
                      />
                      <input
                        placeholder="Max"
                        type="number"
                        value={row.max}
                        onChange={(e) =>
                          setPriceRows((rows) => rows.map((r, i) => (i === idx ? { ...r, max: e.target.value } : r)))
                        }
                        className="col-span-2 rounded-lg border border-gray-300 px-2 py-1.5 text-xs"
                      />
                      <input
                        placeholder="Currency"
                        value={row.currency}
                        onChange={(e) =>
                          setPriceRows((rows) => rows.map((r, i) => (i === idx ? { ...r, currency: e.target.value } : r)))
                        }
                        className="col-span-2 rounded-lg border border-gray-300 px-2 py-1.5 text-xs"
                      />
                      <input
                        placeholder="Unit (optional)"
                        value={row.unit}
                        onChange={(e) =>
                          setPriceRows((rows) => rows.map((r, i) => (i === idx ? { ...r, unit: e.target.value } : r)))
                        }
                        className="col-span-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs"
                      />
                      <button
                        type="button"
                        onClick={() => setPriceRows((rows) => rows.filter((_, i) => i !== idx))}
                        className="col-span-1 flex items-center justify-center text-rose-500 hover:text-rose-700"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="flex justify-end">
              <button
                onClick={() => saveDurationPriceMutation.mutate()}
                disabled={saveDurationPriceMutation.isPending}
                className="px-5 py-2.5 text-xs font-bold text-white bg-[#2E7D4F] hover:bg-[#24653F] rounded-xl shadow-2xs disabled:opacity-50"
              >
                {saveDurationPriceMutation.isPending ? 'Saving...' : 'Save Duration & Price Baseline'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
