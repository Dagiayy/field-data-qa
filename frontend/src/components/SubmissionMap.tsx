import React from 'react';
import { MapContainer, TileLayer, Marker, Popup, Circle } from 'react-leaflet';
import L from 'leaflet';
import type { Media } from '../types';

delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

const submissionIcon = new L.Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const mediaIcon = new L.Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
  iconSize: [20, 32],
  iconAnchor: [10, 32],
  popupAnchor: [1, -28],
  shadowSize: [32, 32],
});

interface SubmissionMapProps {
  submissionGps: { lat: number; lng: number; accuracy_m?: number };
  outletName: string;
  approvedRadiusM?: number;
  geofenceRadiusM?: number;
  media?: Media[];
}

export const SubmissionMap: React.FC<SubmissionMapProps> = ({
  submissionGps,
  outletName,
  approvedRadiusM = 50,
  geofenceRadiusM = 150,
  media = [],
}) => {
  const center: [number, number] = [submissionGps.lat, submissionGps.lng];

  return (
    <div className="bg-white rounded-2xl border border-gray-150 p-4 shadow-sm space-y-3">
      <div className="flex items-center justify-between">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">Location Verification Map</span>
          <h4 className="text-sm font-bold text-gray-800">GPS & Geofence Radii Overlay</h4>
        </div>
        <div className="text-xs text-gray-500 font-mono">
          Lat: {submissionGps.lat.toFixed(4)}, Lng: {submissionGps.lng.toFixed(4)}
        </div>
      </div>

      <div className="h-72 w-full rounded-xl overflow-hidden relative border border-gray-200">
        <MapContainer center={center} zoom={16} scrollWheelZoom={false} className="h-full w-full">
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />

          <Marker position={center} icon={submissionIcon}>
            <Popup>
              <div className="text-xs">
                <strong>Submission Point</strong>
                <div>Outlet: {outletName}</div>
                {submissionGps.accuracy_m && <div>GPS Accuracy: ±{submissionGps.accuracy_m}m</div>}
              </div>
            </Popup>
          </Marker>

          <Circle
            center={center}
            radius={approvedRadiusM}
            pathOptions={{ color: '#2E7D4F', fillColor: '#2E7D4F', fillOpacity: 0.12, weight: 2 }}
          />

          <Circle
            center={center}
            radius={geofenceRadiusM}
            pathOptions={{ color: '#F4A300', fillColor: '#F4A300', fillOpacity: 0.05, weight: 1.5, dashArray: '6, 6' }}
          />

          {media.map((item, idx) => (
            <React.Fragment key={item.media_ref || idx}>
              <Marker position={[item.gps_at_capture.lat, item.gps_at_capture.lng]} icon={mediaIcon}>
                <Popup>
                  <div className="text-xs">
                    <strong>Photo Capture #{idx + 1}</strong>
                    <div>Time: {new Date(item.captured_at).toLocaleTimeString()}</div>
                    {item.baseline && <div>Spot: {item.baseline.spot_label}</div>}
                  </div>
                </Popup>
              </Marker>
              {item.baseline && (
                <Circle
                  center={[item.gps_at_capture.lat, item.gps_at_capture.lng]}
                  radius={15}
                  pathOptions={{ color: '#20C4E8', fillColor: '#20C4E8', fillOpacity: 0.2, weight: 1.5 }}
                />
              )}
            </React.Fragment>
          ))}
        </MapContainer>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 text-[11px] bg-gray-50 p-2.5 rounded-xl border border-gray-100 font-medium text-gray-700">
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full bg-emerald-600 inline-block" />
          <span>Submission GPS</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full border-2 border-[#2E7D4F] bg-[#2E7D4F]/20 inline-block" />
          <span>Outlet Radius ({approvedRadiusM}m)</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full border-2 border-dashed border-[#F4A300] bg-[#F4A300]/10 inline-block" />
          <span>Quest Geofence ({geofenceRadiusM}m)</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full bg-blue-500 inline-block" />
          <span>Photo Capture GPS</span>
        </div>
      </div>
    </div>
  );
};
