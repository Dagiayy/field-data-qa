import React from 'react';
import { QuestBaselinePanel } from '../components/QuestBaselinePanel';
import { MapPin } from 'lucide-react';

/**
 * Baseline Management is quest-first: pick one quest from the list and
 * every baseline it needs — the GPS/reference-photo spot, the expected
 * survey duration range, and the expected price range per SKU — is right
 * there on one screen (see QuestBaselinePanel). No outlet to pick
 * separately, no tabs to switch between.
 */
export const BaselineManagement: React.FC = () => {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-2">
          <MapPin className="w-6 h-6 text-[#2E7D4F]" /> Baseline Management
        </h2>
        <p className="text-xs text-gray-500 mt-0.5">
          Pick a quest — its baseline photo, expected duration, and expected price ranges are all managed right here.
        </p>
      </div>

      <QuestBaselinePanel />
    </div>
  );
};
