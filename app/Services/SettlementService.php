<?php
namespace App\Services;

use App\Models\Settlement;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function createSettlement($collectorId, array $collectionIds = [], array $contractPaymentIds = [], $notes = null)
    {
        return DB::transaction(function () use ($collectorId, $collectionIds, $contractPaymentIds, $notes) {
            $collections = Collection::whereIn('id', $collectionIds)
                ->where('collector_id', $collectorId)
                ->where('is_settled', false)
                ->get();

            $contractPayments = \App\Models\ContractPayment::whereIn('id', $contractPaymentIds)
                ->where('created_by', $collectorId)
                ->where('is_settled', false)
                ->get();

            if ($collections->isEmpty() && $contractPayments->isEmpty()) {
                throw new \Exception('No unsettled items found.');
            }

            $totalCollected = $collections->sum('collected_amount') + $contractPayments->sum('amount');

            $settlement = Settlement::create([
                'collector_id' => $collectorId,
                'settlement_date' => now()->toDateString(),
                'total_collected' => $totalCollected,
                'total_settled' => 0,
                'status' => 'pending',
                'notes' => $notes
            ]);

            if ($collectionIds) {
                $settlement->collections()->attach($collectionIds);
            }
            
            if ($contractPaymentIds) {
                \App\Models\ContractPayment::whereIn('id', $contractPaymentIds)->update([
                    'settlement_id' => $settlement->id
                ]);
            }

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

                \App\Models\ContractPayment::where('settlement_id', $settlement->id)->update([
                    'is_settled' => true,
                    'settled_at' => now()
                ]);
            }

            return $settlement;
        });
    }
}
