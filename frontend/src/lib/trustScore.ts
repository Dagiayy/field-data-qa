import type { Submission, OutletSummary, TrustScoreBreakdown, PaymentStatus } from '../types';

function haversineDistanceMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371000; // Earth radius in meters
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

export function computeTrustScore(submission: Submission, outlet?: OutletSummary): TrustScoreBreakdown {
  const questType = submission.quest.quest_type || 'price_check';

  // 1. Weights based on quest type
  let weights = {
    gps: 0.20,
    time: 0.15,
    photo: 0.20,
    answer: 0.15,
    price: 0.20,
    completeness: 0.10,
  };

  if (questType === 'shelf_audit') {
    weights = { gps: 0.25, time: 0.15, photo: 0.30, answer: 0.10, price: 0.05, completeness: 0.15 };
  } else if (questType === 'agri_verify') {
    weights = { gps: 0.35, time: 0.10, photo: 0.25, answer: 0.15, price: 0.0, completeness: 0.15 };
  }

  // 2. GPS Score & Details
  const shopLat = outlet?.gps_lat ?? submission.gps.lat;
  const shopLng = outlet?.gps_lng ?? submission.gps.lng;

  const distanceToShop = haversineDistanceMeters(shopLat, shopLng, submission.gps.lat, submission.gps.lng);
  
  let photoDistToShop = distanceToShop;
  if (submission.media.length > 0) {
    const firstMediaGps = submission.media[0].gps_at_capture;
    photoDistToShop = haversineDistanceMeters(shopLat, shopLng, firstMediaGps.lat, firstMediaGps.lng);
  }

  // Score calculation: 100 if < 15m, 0 if > 150m
  let gpsScore = 100;
  if (distanceToShop > 15) {
    gpsScore = Math.max(0, Math.round(100 - ((distanceToShop - 15) / 135) * 100));
  }

  // 3. Time Score & Details
  const start = submission.timestamps?.survey_start_at || submission.survey_start_at;
  const end = submission.timestamps?.survey_end_at || submission.survey_end_at;
  
  let durationSeconds = 285; // default 4.75 mins
  if (start && end) {
    durationSeconds = Math.max(1, Math.round((new Date(end).getTime() - new Date(start).getTime()) / 1000));
  }

  const expectedSeconds = 240; // 4 minutes
  let timeScore = 100;
  if (durationSeconds < 45) {
    timeScore = 30; // completed dangerously fast
  } else if (durationSeconds < 120) {
    timeScore = 70;
  } else if (durationSeconds > 1800) {
    timeScore = 60; // took too long (idle)
  }

  // 4. Photo Score & Details
  let photoScore = 95;
  let baselineSimilarity = 0.88;
  let clarityPass = true;

  if (submission.media.length > 0) {
    const mediaWithBaseline = submission.media.find((m) => m.baseline);
    if (mediaWithBaseline?.baseline?.embedding_similarity !== null && mediaWithBaseline?.baseline?.embedding_similarity !== undefined) {
      baselineSimilarity = mediaWithBaseline.baseline.embedding_similarity;
      photoScore = Math.round(baselineSimilarity * 100);
    }
  }

  // Check for flagged blurry photo
  if (submission.flags.some((f) => f.rule_name.includes('photo') || f.rule_name.includes('wrong_location'))) {
    photoScore = Math.min(photoScore, 65);
    clarityPass = false;
  }

  // 5. Answer Score & Details
  const totalAnswers = submission.answers.length;
  const validAnswers = submission.answers.filter((a) => a.value !== undefined && a.value !== '').length;
  const answerScore = totalAnswers > 0 ? Math.round((validAnswers / totalAnswers) * 100) : 100;

  // 6. Price Score & Details
  let priceScore = 95;
  const hasPriceFlag = submission.flags.some((f) => f.rule_name.includes('price'));
  let outliersCount = hasPriceFlag ? 1 : 0;
  if (hasPriceFlag) {
    priceScore = 75;
  }

  // 7. Completeness Score & Details
  const completenessScore = Math.round((validAnswers / Math.max(1, totalAnswers)) * 100);

  // Total Weighted Score
  const totalWeighted =
    gpsScore * weights.gps +
    timeScore * weights.time +
    photoScore * weights.photo +
    answerScore * weights.answer +
    priceScore * weights.price +
    completenessScore * weights.completeness;

  const total_score = Math.round(totalWeighted);

  return {
    total_score,
    gps_score: gpsScore,
    gps_details: {
      shop_actual_gps: { lat: shopLat, lng: shopLng },
      submission_gps: { lat: submission.gps.lat, lng: submission.gps.lng, accuracy_m: submission.gps.accuracy_m },
      photo_gps: {
        lat: submission.media[0]?.gps_at_capture.lat ?? submission.gps.lat,
        lng: submission.media[0]?.gps_at_capture.lng ?? submission.gps.lng,
      },
      distance_to_shop_m: Math.round(distanceToShop * 10) / 10,
      photo_distance_to_shop_m: Math.round(photoDistToShop * 10) / 10,
    },
    time_score: timeScore,
    time_details: {
      duration_seconds: durationSeconds,
      expected_seconds: expectedSeconds,
      passed: timeScore >= 70,
    },
    photo_score: photoScore,
    photo_details: {
      clarity_pass: clarityPass,
      baseline_similarity: baselineSimilarity,
    },
    answer_score: answerScore,
    answer_details: {
      valid_answers_count: validAnswers,
      total_answers_count: totalAnswers,
    },
    price_score: priceScore,
    price_details: {
      outliers_count: outliersCount,
      total_prices_count: submission.prices.length,
    },
    completeness_score: completenessScore,
    completeness_details: {
      completed_fields: validAnswers,
      required_fields: totalAnswers,
    },
    weights,
  };
}

export function derivePaymentStatus(status: Submission['status'], trustScore: number): PaymentStatus {
  if (status === 'approved') {
    return trustScore >= 80 ? 'paid' : 'under_review';
  }
  if (status === 'rejected') {
    return 'rejected';
  }
  if (status === 'backcheck') {
    return 'under_review';
  }
  return 'pending';
}
