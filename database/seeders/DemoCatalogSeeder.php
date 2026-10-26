<?php

namespace Database\Seeders;

use App\Enums\InsuranceType;
use App\Enums\PackageType;
use App\Enums\PriceUnit;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Insurance;
use App\Models\Package;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Catalogo dimostrativo in Egitto: soggiorni (solo soggiorno e volo + hotel),
 * escursioni e transfer, con tipologie di camera, allotment, voli e
 * assicurazioni. Nomi di hotel e strutture di fantasia. Non crea utenti.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Package::withTrashed()->where('slug', 'hurghada-all-inclusive')->exists()) {
            $this->command?->warn('Catalogo demo già presente: nessuna modifica.');

            return;
        }

        $this->seedInsurances();

        foreach ($this->catalog() as $data) {
            $destination = Destination::create($data['destination']);

            $hotels = collect($data['hotels'])
                ->mapWithKeys(fn (array $hotel) => [$hotel['name'] => $destination->hotels()->create($hotel)]);

            $hotels->each(fn (Hotel $hotel) => $this->seedRoomTypes($hotel));

            if ($data['flights']) {
                $this->seedFlights($destination, $data['flights']['base_price']);
            }

            foreach ($data['packages'] as $packageData) {
                $periods = $packageData['periods'];
                $hotelName = $packageData['hotel'] ?? null;
                unset($packageData['periods'], $packageData['hotel']);

                /** @var Package $package */
                $package = $destination->packages()->create([
                    ...$packageData,
                    'hotel_id' => $hotelName ? $hotels[$hotelName]->id : null,
                    'valid_from' => $periods[0]['start_date'],
                    'valid_to' => end($periods)['end_date'],
                    'cancellation_policy' => "Penali per persona in caso di annullamento:\n- fino a 30 giorni prima della partenza: 10%\n- da 29 a 15 giorni: 30%\n- da 14 a 7 giorni: 50%\n- da 6 giorni alla partenza: 100%",
                ]);

                $package->periods()->createMany($periods);
            }
        }
    }

    private function seedInsurances(): void
    {
        Insurance::create(['name' => 'Medico-bagaglio', 'type' => InsuranceType::MedicalLuggage, 'description' => 'Spese mediche fino a € 30.000 e bagaglio fino a € 1.000.', 'price' => 29]);
        Insurance::create(['name' => 'All risk medico-bagaglio-annullamento', 'type' => InsuranceType::AllRisk, 'description' => 'Spese mediche illimitate, bagaglio fino a € 2.000 e rimborso delle penali di annullamento.', 'price' => 59]);
    }

    /**
     * Tipologie di camera con allotment di un anno (dal 1° ottobre).
     */
    private function seedRoomTypes(Hotel $hotel): void
    {
        $y = now()->year;
        $types = [
            ['name' => 'Standard', 'supplement' => 0, 'rooms' => 10, 'description' => 'Camera doppia con possibilità di 3° e 4° letto.'],
            ['name' => 'Superior vista mare', 'supplement' => 15, 'rooms' => 6, 'description' => 'Camera con balcone e vista mare.'],
            ['name' => 'Deluxe vista mare', 'supplement' => 30, 'rooms' => 4, 'description' => 'Camera ampia con terrazza fronte mare.'],
        ];

        foreach ($types as $order => $type) {
            $hotel->roomTypes()->create([
                'name' => $type['name'],
                'description' => $type['description'],
                'supplement' => $type['supplement'],
                'max_occupancy' => 4,
                'sort_order' => $order,
            ])->allotments()->create([
                'start_date' => "{$y}-10-01",
                'end_date' => ($y + 1).'-09-30',
                'rooms' => $type['rooms'],
            ]);
        }
    }

    /**
     * Voli A/R di 7 notti ogni domenica per 8 settimane, da Napoli, Roma, Milano e Bari.
     */
    private function seedFlights(Destination $destination, float $basePrice): void
    {
        $firstSunday = today()->addWeek()->next(CarbonInterface::SUNDAY);
        $airportPrice = ['NAP' => 0, 'FCO' => 10, 'MXP' => 20, 'BRI' => -10];

        for ($week = 0; $week < 8; $week++) {
            $outbound = $firstSunday->copy()->addWeeks($week);

            foreach ($airportPrice as $airport => $delta) {
                $destination->flights()->create([
                    'departure_airport' => $airport,
                    'outbound_date' => $outbound,
                    'return_date' => $outbound->copy()->addDays(7),
                    'carrier' => 'Charter Queen Tour',
                    'outbound_details' => 'Partenza ore 08:30',
                    'return_details' => 'Partenza ore 15:00',
                    'seats' => 30,
                    'adult_price' => $basePrice + $delta,
                    'child_price' => round(($basePrice + $delta) * 0.75),
                    'infant_price' => 50,
                ]);
            }
        }
    }

    /**
     * Listino su cinque stagioni a partire dall'autunno dell'anno corrente.
     * Il prezzo base è quello di bassa stagione (a notte per i soggiorni).
     *
     * @return array<int, array<string, mixed>>
     */
    private function seasons(float $base, bool $perPerson = true): array
    {
        $y = now()->year;
        $seasons = [
            ['Autunno', "{$y}-10-01", "{$y}-12-19", 1.0],
            ['Natale e Capodanno', "{$y}-12-20", ($y + 1).'-01-06', 1.4],
            ['Inverno', ($y + 1).'-01-07', ($y + 1).'-03-31', 1.05],
            ['Primavera', ($y + 1).'-04-01', ($y + 1).'-06-30', 1.15],
            ['Estate', ($y + 1).'-07-01', ($y + 1).'-09-30', 1.25],
        ];

        return array_map(function (array $season) use ($base, $perPerson) {
            [$name, $start, $end, $factor] = $season;
            $adult = round($base * $factor);

            return [
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'adult_price' => $adult,
                'extra_bed_adult_price' => $perPerson ? round($adult * 0.8) : null,
                'child_price' => $perPerson ? round($adult * 0.5) : null,
                'infant_price' => null,
                'single_supplement' => $perPerson ? round($adult * 0.3) : null,
            ];
        }, $seasons);
    }

    private function image(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/1200/800";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        $stayExcluded = ["Volo A/R dall'Italia", 'Transfer aeroportuale se non richiesto', 'Escursioni facoltative', 'Visto e assicurazione'];

        return [
            [
                'destination' => [
                    'name' => 'Hurghada',
                    'country' => 'Egitto',
                    'description' => 'Mar Rosso, resort sul mare e partenza ideale per Luxor e il deserto.',
                    'cover_image' => $this->image('hurghada'),
                    'sort_order' => 1,
                ],
                'hotels' => [[
                    'name' => 'Red Sea Paradise Resort',
                    'stars' => 5,
                    'address' => 'Sahl Hasheesh, Hurghada',
                    'description' => 'Resort sul mare con spiaggia privata, piscine e spa.',
                    'amenities' => ['Spiaggia privata', 'Piscine', 'Spa', 'Wi-Fi', 'Animazione'],
                    'images' => [$this->image('redsea1'), $this->image('redsea2')],
                ]],
                'flights' => ['base_price' => 290],
                'packages' => [
                    [
                        'type' => PackageType::Stay,
                        'price_unit' => PriceUnit::PerPerson,
                        'hotel' => 'Red Sea Paradise Resort',
                        'title' => 'Hurghada All Inclusive',
                        'slug' => 'hurghada-all-inclusive',
                        'location' => 'Hurghada · Mar Rosso',
                        'badge' => 'Più richiesto',
                        'cover_image' => $this->image('hurghada-ai'),
                        'gallery' => [$this->image('hurghada-g1'), $this->image('hurghada-g2'), $this->image('hurghada-g3')],
                        'nights' => 7,
                        'board' => 'All Inclusive',
                        'accommodation' => 'Resort 5 stelle',
                        'period_label' => "Tutto l'anno",
                        'departures' => ['Aeroporto di Hurghada'],
                        'short_description' => 'Sette notti in resort sul Mar Rosso, con formula All Inclusive e assistenza dedicata.',
                        'description' => "Una settimana a Hurghada pensata per chi vuole mare, comfort e zero pensieri una volta arrivato in Egitto.\n\nSelezioniamo il resort più adatto alle tue esigenze e possiamo aggiungere transfer privato ed escursioni su misura.",
                        'highlights' => ['Resort sul mare', 'All Inclusive', 'Assistenza locale', 'Formula flessibile'],
                        'included' => ['7 pernottamenti', 'Trattamento All Inclusive', 'Assistenza in loco', 'Selezione personalizzata del resort'],
                        'excluded' => $stayExcluded,
                        'itinerary' => [
                            ['day' => 'Giorno 1', 'title' => 'Benvenuti a Hurghada', 'description' => 'Arrivo autonomo, eventuale transfer riservato e sistemazione nel resort.'],
                            ['day' => 'Giorni 2–3', 'title' => 'Mar Rosso e relax', 'description' => 'Tempo libero tra spiaggia, piscina e servizi All Inclusive del resort.'],
                            ['day' => 'Giorni 4–6', 'title' => "L'Egitto che scegli tu", 'description' => 'Possibilità di aggiungere Orange Bay, safari nel deserto o una giornata a Luxor.'],
                            ['day' => 'Giorni 7–8', 'title' => 'Ultimi momenti sul mare', 'description' => "Relax, check-out ed eventuale transfer privato verso l'aeroporto."],
                        ],
                        'transfer_included' => false,
                        'transfer_price' => 15,
                        'flight_hotel_enabled' => true,
                        'min_nights' => 3,
                        'is_featured' => true,
                        'sort_order' => 1,
                        'periods' => $this->seasons(70),
                    ],
                    [
                        'type' => PackageType::Excursion,
                        'price_unit' => PriceUnit::PerPerson,
                        'title' => 'Orange Bay in barca',
                        'slug' => 'orange-bay-in-barca',
                        'location' => 'Giftun Island · Hurghada',
                        'badge' => 'Imperdibile',
                        'cover_image' => $this->image('orangebay'),
                        'nights' => 0,
                        'board' => 'Pranzo incluso',
                        'accommodation' => 'Barca con ponte panoramico',
                        'period_label' => "Tutto l'anno",
                        'departures' => ['Porto di Hurghada', 'Hotel Hurghada'],
                        'short_description' => "Una giornata nell'isola dalle acque turchesi, con snorkeling e pranzo a bordo.",
                        'description' => "Partenza in barca verso Orange Bay, una delle spiagge più belle del Mar Rosso.\n\nDue soste snorkeling sulla barriera corallina, pranzo a bordo e tempo libero in spiaggia.",
                        'highlights' => ['Acque cristalline', 'Due soste snorkeling', 'Pranzo a bordo', 'Pick-up in hotel'],
                        'included' => ['Pick-up e rientro in hotel', 'Pranzo a bordo e bevande analcoliche', 'Attrezzatura snorkeling', 'Assistente in italiano'],
                        'excluded' => ['Tassa di ingresso al parco marino', 'Mance', 'Foto e video'],
                        'itinerary' => [
                            ['day' => 'Mattina', 'title' => 'Partenza dal porto', 'description' => 'Pick-up in hotel e imbarco verso Giftun Island.'],
                            ['day' => 'Mezzogiorno', 'title' => 'Snorkeling e pranzo', 'description' => 'Soste sulla barriera corallina e pranzo a bordo.'],
                            ['day' => 'Pomeriggio', 'title' => 'Relax a Orange Bay', 'description' => 'Tempo libero in spiaggia e rientro in hotel.'],
                        ],
                        'sort_order' => 2,
                        'periods' => $this->seasons(45),
                    ],
                    [
                        'type' => PackageType::Excursion,
                        'price_unit' => PriceUnit::PerPerson,
                        'title' => 'Luxor in giornata',
                        'slug' => 'luxor-in-giornata',
                        'location' => 'Luxor · Valle del Nilo',
                        'badge' => 'Premium',
                        'cover_image' => $this->image('luxor'),
                        'nights' => 0,
                        'board' => 'Pranzo incluso',
                        'accommodation' => 'Bus GT con guida egittologa italiana',
                        'period_label' => "Tutto l'anno",
                        'departures' => ['Hotel Hurghada'],
                        'short_description' => 'Templi di Karnak e Luxor e Valle dei Re in una giornata con guida in italiano.',
                        'description' => "Un viaggio nella storia dell'antico Egitto: la Valle dei Re, il Tempio di Karnak e il Tempio di Luxor.\n\nGuida egittologa in italiano e pranzo in ristorante sul Nilo.",
                        'highlights' => ['Valle dei Re', 'Tempio di Karnak', 'Guida in italiano', 'Pranzo sul Nilo'],
                        'included' => ['Trasporto in bus GT', 'Guida egittologa in italiano', 'Ingressi ai siti', 'Pranzo in ristorante'],
                        'excluded' => ['Bevande', 'Mance', 'Ingresso alle tombe speciali'],
                        'itinerary' => [
                            ['day' => 'Alba', 'title' => 'Partenza per Luxor', 'description' => 'Pick-up in hotel e trasferimento verso Luxor.'],
                            ['day' => 'Mattina', 'title' => 'Valle dei Re', 'description' => 'Visita alle tombe dei faraoni e al Tempio di Hatshepsut.'],
                            ['day' => 'Pomeriggio', 'title' => 'Karnak e Luxor', 'description' => 'Pranzo sul Nilo e visita ai templi.'],
                        ],
                        'sort_order' => 3,
                        'periods' => $this->seasons(95),
                    ],
                    [
                        'type' => PackageType::Transfer,
                        'price_unit' => PriceUnit::PerService,
                        'title' => 'Transfer privato aeroporto di Hurghada',
                        'slug' => 'transfer-privato-aeroporto-hurghada',
                        'location' => 'Hurghada',
                        'cover_image' => $this->image('transfer-hrg'),
                        'nights' => 0,
                        'board' => 'Servizio privato',
                        'accommodation' => 'Auto privata con autista',
                        'period_label' => "Tutto l'anno",
                        'departures' => ['Aeroporto di Hurghada', 'Hotel Hurghada'],
                        'short_description' => "Trasferimento privato tra l'aeroporto e il tuo hotel, senza attese.",
                        'description' => "L'autista ti aspetta in aeroporto con un cartello nominativo e ti accompagna direttamente in hotel.\n\nPrezzo per tratta, valido fino a 4 persone con bagagli.",
                        'highlights' => ['Nessuna attesa', 'Auto privata', 'Fino a 4 persone', 'Assistenza 24/7'],
                        'included' => ['Auto privata con aria condizionata', 'Autista dedicato', 'Accoglienza in aeroporto'],
                        'excluded' => ['Mance', 'Attese oltre 60 minuti dal volo'],
                        'itinerary' => [],
                        'sort_order' => 4,
                        'periods' => $this->seasons(30, perPerson: false),
                    ],
                ],
            ],
            [
                'destination' => [
                    'name' => 'Sharm el Sheikh',
                    'country' => 'Egitto',
                    'description' => 'Barriera corallina, Ras Mohammed e mare tutto l\'anno.',
                    'cover_image' => $this->image('sharm'),
                    'sort_order' => 2,
                ],
                'hotels' => [[
                    'name' => 'Coral Garden Resort & Spa',
                    'stars' => 5,
                    'address' => 'Nabq Bay, Sharm el Sheikh',
                    'description' => 'Resort con accesso diretto alla barriera corallina e spa.',
                    'amenities' => ['Barriera corallina', 'Piscina riscaldata', 'Spa', 'Wi-Fi'],
                    'images' => [$this->image('coral1'), $this->image('coral2')],
                ]],
                'flights' => ['base_price' => 310],
                'packages' => [
                    [
                        'type' => PackageType::Stay,
                        'price_unit' => PriceUnit::PerPerson,
                        'hotel' => 'Coral Garden Resort & Spa',
                        'title' => 'Sharm el Sheikh Resort & Spa',
                        'slug' => 'sharm-el-sheikh-resort-spa',
                        'location' => 'Nabq Bay · Sharm el Sheikh',
                        'badge' => 'Premium',
                        'cover_image' => $this->image('sharm-spa'),
                        'gallery' => [$this->image('sharm-g1'), $this->image('sharm-g2')],
                        'nights' => 7,
                        'board' => 'All Inclusive',
                        'accommodation' => 'Resort 5 stelle con spa',
                        'departures' => ['Aeroporto di Sharm el Sheikh'],
                        'short_description' => 'Una settimana sulla barriera corallina, con spa e formula All Inclusive.',
                        'description' => "Sette notti in un resort affacciato sulla barriera corallina di Nabq Bay.\n\nIdeale per chi ama lo snorkeling direttamente dalla spiaggia e qualche ora di relax in spa.",
                        'highlights' => ['Barriera dalla spiaggia', 'Spa inclusa', 'All Inclusive', 'Assistenza locale'],
                        'included' => ['7 pernottamenti', 'Trattamento All Inclusive', 'Un ingresso alla spa', 'Assistenza in loco'],
                        'excluded' => $stayExcluded,
                        'itinerary' => [
                            ['day' => 'Giorno 1', 'title' => 'Arrivo a Sharm', 'description' => 'Arrivo autonomo e sistemazione nel resort.'],
                            ['day' => 'Giorni 2–7', 'title' => 'Mare e barriera', 'description' => 'Snorkeling dalla spiaggia, spa e possibilità di escursioni a Ras Mohammed.'],
                            ['day' => 'Giorno 8', 'title' => 'Partenza', 'description' => "Check-out ed eventuale transfer verso l'aeroporto."],
                        ],
                        'transfer_included' => true,
                        'flight_hotel_enabled' => true,
                        'min_nights' => 3,
                        'is_featured' => true,
                        'sort_order' => 5,
                        'periods' => $this->seasons(84),
                    ],
                ],
            ],
            [
                'destination' => [
                    'name' => 'Marsa Alam',
                    'country' => 'Egitto',
                    'description' => 'Fondali incontaminati, delfini e tartarughe marine.',
                    'cover_image' => $this->image('marsaalam'),
                    'sort_order' => 3,
                ],
                'hotels' => [[
                    'name' => 'Dolphin Bay Resort',
                    'stars' => 4,
                    'address' => 'Port Ghalib, Marsa Alam',
                    'description' => 'Resort con pontile sulla barriera, ideale per famiglie.',
                    'amenities' => ['Pontile sulla barriera', 'Miniclub', 'Piscina', 'Wi-Fi'],
                    'images' => [$this->image('dolphin1')],
                ]],
                'flights' => null,
                'packages' => [
                    [
                        'type' => PackageType::Stay,
                        'price_unit' => PriceUnit::PerPerson,
                        'hotel' => 'Dolphin Bay Resort',
                        'title' => 'Marsa Alam Family',
                        'slug' => 'marsa-alam-family',
                        'location' => 'Port Ghalib · Marsa Alam',
                        'cover_image' => $this->image('marsa-family'),
                        'gallery' => [$this->image('marsa-g1'), $this->image('marsa-g2')],
                        'nights' => 7,
                        'board' => 'Mezza pensione',
                        'accommodation' => 'Resort 4 stelle con miniclub',
                        'departures' => ['Aeroporto di Marsa Alam'],
                        'short_description' => 'Vacanza per famiglie sul mare più incontaminato del Mar Rosso.',
                        'description' => "Un resort pensato per le famiglie, con miniclub e pontile direttamente sulla barriera.\n\nI bambini fino a 17 anni hanno una quota dedicata.",
                        'highlights' => ['Ideale per famiglie', 'Miniclub', 'Pontile sulla barriera', 'Quota bambini ridotta'],
                        'included' => ['7 pernottamenti', 'Mezza pensione', 'Miniclub', 'Assistenza in loco'],
                        'excluded' => $stayExcluded,
                        'itinerary' => [],
                        'transfer_included' => true,
                        'min_nights' => 3,
                        'sort_order' => 6,
                        'periods' => $this->seasons(74),
                    ],
                    [
                        'type' => PackageType::Transfer,
                        'price_unit' => PriceUnit::PerService,
                        'title' => 'Transfer privato aeroporto di Marsa Alam',
                        'slug' => 'transfer-privato-aeroporto-marsa-alam',
                        'location' => 'Marsa Alam',
                        'cover_image' => $this->image('transfer-rmf'),
                        'nights' => 0,
                        'board' => 'Servizio privato',
                        'accommodation' => 'Minivan privato con autista',
                        'period_label' => "Tutto l'anno",
                        'departures' => ['Aeroporto di Marsa Alam'],
                        'short_description' => "Trasferimento privato tra l'aeroporto di Marsa Alam e il tuo hotel.",
                        'description' => 'Prezzo per tratta, valido fino a 7 persone con bagagli.',
                        'highlights' => ['Nessuna attesa', 'Minivan privato', 'Fino a 7 persone'],
                        'included' => ['Minivan con aria condizionata', 'Autista dedicato', 'Accoglienza in aeroporto'],
                        'excluded' => ['Mance'],
                        'itinerary' => [],
                        'sort_order' => 7,
                        'periods' => $this->seasons(45, perPerson: false),
                    ],
                ],
            ],
        ];
    }
}
