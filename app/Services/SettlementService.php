<?php
namespace App\Services;

use App\Models\Settlement;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function createSettlement($collectorId, array $collectionIds, $notes = null)
    {
        return DB::transaction(function () use ($collectorId, $collectionIds, $notes) {
            $collections = Collection::whereIn('id', $collectionIds)
                ->where('collector_id', $collectorId)
                ->where('is_settled', false)
                ->get();

            if ($collections->isEmpty()) {
                throw new \Exception('No unsettled collections found.');
            }

            $totalCollected = $collections->sum('collected_amount');

            $settlement = Settlement::create([
                'collector_id' => $collectorId,
                'settlement_date' => now()->toDateString(),
                'total_collected' => $totalCollected,
                'total_settled' => 0, // Pending confirmation
                'status' => 'pending',
                'notes' => $notes
            ]);

            $settlement->collections()->attach($collectionIds);
            
            // Note: we don't mark as settled until confirmed.
            return $settlement;
        });
    }

    public function confirmSettlement(Settlement $settlement, $confirmedById, $totalSettled)
    {
        return DB::transaction(function () use ($settlement, $confirmedById, $totalSettled) {
            $status = ($totalSettled == $settlement->total_collected) ? 'confirmed' : 'discrepancy';
            
            $settlement->update([
                'total_settled' => $totalSettled,
                'status' => $status,
                'confirmed_by' => $confirmedById
            ]);

            if ($status === 'confirmed') {
                foreach ($settlement->collections as $collection) {
                    $collection->update([
                        'is_settled' => true,
                        'settled_at' => now()
                    ]);
                }
            }

            return $settlement;
        });
    }
}
