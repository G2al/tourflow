<?php

namespace App\Services;

use App\Enums\AgeCategory;
use App\Models\Excursion;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\PackagePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Calcola il prezzo di un pacchetto per una data di partenza e un gruppo di
 * partecipanti (una camera). Le regole sono quelle classiche da listino:
 *
 * - prodotti "a servizio" (es. transfer): un unico prezzo per il gruppo;
 * - i primi due adulti pagano la quota base (adulto in doppia);
 * - dal terzo adulto in poi si applica la quota 3°/4° letto (se presente);
 * - bambini e infant pagano la loro quota (fallback: adulto / gratis);
 * - un adulto da solo (senza bambini) paga il supplemento singola;
 * - trasferimenti non inclusi e quota d'iscrizione si sommano per ogni
 *   partecipante pagante (infant esclusi);
 * - escursioni e assicurazioni sono per persona (infant esclusi).
 */
class PriceCalculator
{
    /**
     * @param  array<int, string>  $birthDates  date di nascita (Y-m-d) dei partecipanti
     * @param  array<int, int>  $excursionIds
     * @param  array<int, int>  $insuranceIds
     * @return array<string, mixed>
     */
    public function calculate(
        Package $package,
        string $departureDate,
        array $birthDates,
        array $excursionIds = [],
        array $insuranceIds = [],
        bool $net = false,
    ): array {
        $package->loadMissing(['periods', 'excursions', 'insurances']);

        $departure = CarbonImmutable::parse($departureDate)->startOfDay();
        $period = $this->resolvePeriod($package, $departure);

        $participants = $this->categorize($package, $departure, $birthDates);
        $adults = collect($participants)->where('age_category', AgeCategory::Adult)->count();
        $children = collect($participants)->where('age_category', AgeCategory::Child)->count();

        if ($adults === 0) {
            throw ValidationException::withMessages([
                'participants' => 'È necessario almeno un partecipante adulto.',
            ]);
        }

        // Prodotti "a servizio" (es. transfer): un unico prezzo per tutto il gruppo.
        $service = $package->isPerService()
            ? ['label' => 'Prezzo del servizio', 'amount' => $period->price('adult_price', $net)]
            : null;

        $adultIndex = 0;
        foreach ($participants as &$participant) {
            $items = $service === null
                ? $this->personItems($package, $period, $participant['age_category'], $adultIndex, $adults, $children, $net)
                : [];

            $participant['items'] = $items;
            $participant['total'] = round(array_sum(array_column($items, 'amount')), 2);
        }
        unset($participant);

        $extras = [
            ...$this->excursions($package, $excursionIds, $adults, $children, $net),
            ...$this->insurances($package, $insuranceIds, $adults + $children, $net),
        ];

        $serviceTotal = (float) ($service['amount'] ?? 0);
        $participantsTotal = round(array_sum(array_column($participants, 'total')), 2);
        $extrasTotal = round(array_sum(array_column($extras, 'total')), 2);

        return [
            'price_type' => $net ? 'net' : 'gross',
            'price_unit' => $package->price_unit->value,
            'currency' => 'EUR',
            'departure_date' => $departure->toDateString(),
            'return_date' => $departure->addDays($package->nights)->toDateString(),
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
            ],
            'service' => $service,
            'participants' => array_map(fn ($p) => [...$p, 'age_category' => $p['age_category']->value], $participants),
            'extras' => $extras,
            'totals' => [
                'service' => $serviceTotal,
                'participants' => $participantsTotal,
                'extras' => $extrasTotal,
                'total' => round($serviceTotal + $participantsTotal + $extrasTotal, 2),
            ],
        ];
    }

    private function resolvePeriod(Package $package, CarbonImmutable $departure): PackagePeriod
    {
        if ($departure->lt(today())) {
            throw ValidationException::withMessages(['departure_date' => 'La data di partenza non può essere nel passato.']);
        }

        if (! $departure->between($package->valid_from, $package->valid_to)) {
            throw ValidationException::withMessages([
                'departure_date' => "Il pacchetto è valido dal {$package->valid_from->format('d/m/Y')} al {$package->valid_to->format('d/m/Y')}.",
            ]);
        }

        return $package->periodFor($departure) ?? throw ValidationException::withMessages([
            'departure_date' => 'Nessuna tariffa disponibile per la data selezionata.',
        ]);
    }

    /**
     * @param  array<int, string>  $birthDates
     * @return array<int, array<string, mixed>>
     */
    private function categorize(Package $package, CarbonImmutable $departure, array $birthDates): array
    {
        if ($birthDates === []) {
            throw ValidationException::withMessages(['participants' => 'Inserire almeno un partecipante.']);
        }

        $participants = [];
        foreach (array_values($birthDates) as $index => $birthDate) {
            $birth = CarbonImmutable::parse($birthDate);
            $age = (int) $birth->diffInYears($departure);

            $participants[] = [
                'index' => $index,
                'birth_date' => $birth->toDateString(),
                'age' => $age,
                'age_category' => $package->ageCategoryFor($age),
            ];
        }

        return $participants;
    }

    /**
     * Voci di prezzo di un singolo partecipante (prodotti "per persona").
     *
     * @return array<int, array{label: string, amount: float}>
     */
    private function personItems(Package $package, PackagePeriod $period, AgeCategory $category, int &$adultIndex, int $adults, int $children, bool $net): array
    {
        $items = [];

        if ($category === AgeCategory::Adult) {
            $adultIndex++;
            $items[] = $adultIndex <= 2
                ? ['label' => 'Quota base adulto', 'amount' => $period->price('adult_price', $net)]
                : ['label' => 'Quota 3°/4° letto adulto', 'amount' => $period->price('extra_bed_adult_price', $net, $period->price('adult_price', $net))];

            if ($adults === 1 && $children === 0 && ($supplement = $period->price('single_supplement', $net))) {
                $items[] = ['label' => 'Supplemento singola', 'amount' => $supplement];
            }
        } elseif ($category === AgeCategory::Child) {
            $items[] = ['label' => 'Quota bambino', 'amount' => $period->price('child_price', $net, $period->price('adult_price', $net))];
        } else {
            return [['label' => 'Quota infant', 'amount' => $period->price('infant_price', $net, 0.0)]];
        }

        return [...$items, ...$this->perPersonFees($package, $net)];
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function perPersonFees(Package $package, bool $net): array
    {
        $fees = [];

        if (! $package->transfer_included && $package->transfer_price_gross !== null) {
            $fees[] = ['label' => 'Trasferimenti', 'amount' => (float) ($net ? $package->transfer_price_net : $package->transfer_price_gross)];
        }

        $fees[] = ['label' => "Quota d'iscrizione", 'amount' => (float) ($net ? $package->registration_fee_net : $package->registration_fee_gross)];

        return array_values(array_filter($fees, fn (array $fee) => $fee['amount'] > 0));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function excursions(Package $package, array $ids, int $adults, int $children, bool $net): array
    {
        $available = $package->excursions->where('is_active', true)->keyBy('id');

        return collect(array_unique($ids))->map(function (int $id) use ($available, $adults, $children, $net) {
            /** @var Excursion|null $excursion */
            $excursion = $available->get($id) ?? throw ValidationException::withMessages([
                'excursions' => "L'escursione selezionata (#{$id}) non è disponibile per questo pacchetto.",
            ]);

            return [
                'type' => 'excursion',
                'id' => $excursion->id,
                'name' => $excursion->name,
                'quantity' => $adults + $children,
                'total' => round($adults * $excursion->adultPrice($net) + $children * $excursion->childPrice($net), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function insurances(Package $package, array $ids, int $persons, bool $net): array
    {
        $available = $package->insurances->where('is_active', true)->keyBy('id');

        return collect(array_unique($ids))->map(function (int $id) use ($available, $persons, $net) {
            /** @var Insurance|null $insurance */
            $insurance = $available->get($id) ?? throw ValidationException::withMessages([
                'insurances' => "L'assicurazione selezionata (#{$id}) non è disponibile per questo pacchetto.",
            ]);

            return [
                'type' => 'insurance',
                'id' => $insurance->id,
                'name' => $insurance->name,
                'quantity' => $persons,
                'total' => round($persons * $insurance->price($net), 2),
            ];
        })->values()->all();
    }
}
