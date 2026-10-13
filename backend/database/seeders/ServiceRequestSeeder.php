<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Database\Seeder;

class ServiceRequestSeeder extends Seeder
{
    /**
     * Seed realistic demo service requests around Finland. The coordinates
     * are OpenStreetMap's (Nominatim) for each address.
     *
     * Due dates are relative to today so the demo always has upcoming,
     * due-soon and overdue requests. Two requests have no location, so the
     * map also shows how requests without one are handled.
     */
    public function run(): void
    {
        $requests = [
            ['Vaihda ilmansuodatin', 'Vaihda varaston ilmanvaihtokoneen ilmansuodatin.', 'high', 'open', 8, ['Tikkurilantie 44', '01300', 'Vantaa', 60.2891, 25.0371]],
            ['Tarkasta varaston nosto-ovi', 'Nosto-ovi pysähtyy puoliväliin. Tarkasta jouset ja turvatunnistimet.', 'high', 'in_progress', 2, ['Hatanpään valtatie 24', '33100', 'Tampere', 61.4910, 23.7721]],
            ['Vaihda toimiston loisteputket', 'Toisen kerroksen neuvotteluhuoneen kaksi loisteputkea vilkkuu.', 'low', 'completed', -5, ['Mannerheimintie 12', '00100', 'Helsinki', 60.1680, 24.9407]],
            ['Korjaa vuotava hana', 'Taukotilan keittiön hana tiputtaa jatkuvasti.', 'normal', 'open', -3, ['Itäinen Rantakatu 8', '20700', 'Turku', 60.4487, 22.2714]],
            ['Huolla trukki', 'Trukin määräaikaishuolto: öljyt, renkaat ja jarrut.', 'normal', 'open', 14, ['Isokatu 32', '90100', 'Oulu', 65.0103, 25.4701]],
            ['Tyhjennä rasvanerotin', 'Henkilöstöravintolan rasvanerottimen tyhjennys ja tarkastus.', 'high', 'open', -1, ['Kauppakatu 19', '40100', 'Jyväskylä', 62.2409, 25.7434]],
            ['Testaa paloilmoitinjärjestelmä', 'Kuukausittainen paloilmoitinjärjestelmän koestus ja kirjaus.', 'high', 'completed', -10, ['Tekniikantie 12', '02150', 'Espoo', 60.1839, 24.8143]],
            ['Lumityöt pihalla', 'Lastauslaiturin edustan auraus ja hiekoitus ennen aamuvuoroa.', 'normal', 'completed', -2, ['Koskikatu 25', '96200', 'Rovaniemi', 66.5025, 25.7245]],
            ['Vaihda kulunvalvonnan kortinlukija', 'Pääoven kortinlukija ei tunnista kaikkia kulkukortteja.', 'normal', 'in_progress', 5, ['Puijonkatu 20', '70100', 'Kuopio', 62.8926, 27.6793]],
            ['Puhdista ilmanvaihtokanavat', 'Toimistosiiven ilmanvaihtokanavien nuohous ja puhdistus.', 'low', 'open', 30, ['Aleksanterinkatu 18', '15140', 'Lahti', 60.9833, 25.6625]],
            ['Korjaa lastauslaiturin tasoitin', 'Laiturin 3 tasoitin ei nouse täysin ylös.', 'high', 'open', -6, ['Hovioikeudenpuistikko 15', '65100', 'Vaasa', 63.0964, 21.6146]],
            ['Vaihda WC:n poistoilmapuhallin', 'Alakerran WC:n poistoilmapuhallin pitää kovaa ääntä.', 'low', 'open', 21, ['Torikatu 30', '80100', 'Joensuu', 62.6035, 29.7661]],
            ['Tarkasta ensiapukaapit', 'Täydennä ensiapukaappien tarvikkeet ja tarkasta vanhenemispäivät.', 'normal', 'completed', -14, null],
            ['Maalaa varaston lattiamerkinnät', 'Kulkuväylien lattiamerkinnät ovat kuluneet näkymättömiin.', 'low', 'in_progress', 12, ['Yrjönkatu 10', '28100', 'Pori', 61.4861, 21.7981]],
            ['Säädä toimiston lämmitys', 'Avotoimiston lämpötila on aamuisin alle 19 astetta.', 'normal', 'open', 4, null],
        ];

        // Days since each request was created. Requests from the last 7 days
        // that aren't completed show as new ("Uusi") in the app.
        $createdDaysAgo = [
            'Vaihda ilmansuodatin' => 1,
            'Tarkasta varaston nosto-ovi' => 3,
            'Huolla trukki' => 2,
            'Vaihda WC:n poistoilmapuhallin' => 5,
            'Säädä toimiston lämmitys' => 0,
        ];

        // Made-up customers (example.com addresses, placeholder names), and the
        // demo requests each belongs to, by region.
        $customers = [
            'Esimerkki Kiinteistöt Oy' => [['Maija Mallikas', 'laskutus@esimerkkikiinteistot.example.com', 'Mannerheimintie 12', '00100', 'Helsinki', 14],
                ['Vaihda ilmansuodatin', 'Vaihda toimiston loisteputket', 'Testaa paloilmoitinjärjestelmä', 'Tarkasta ensiapukaapit', 'Säädä toimiston lämmitys']],
            'Pirkan Demohalli Oy' => [['Matti Meikäläinen', 'reskontra@pirkandemohalli.example.com', 'Hatanpään valtatie 24', '33100', 'Tampere', 30],
                ['Tarkasta varaston nosto-ovi', 'Korjaa vuotava hana', 'Maalaa varaston lattiamerkinnät']],
            'Pohjoisen Testivarasto Oy' => [['Liisa Esimerkki', 'laskut@pohjoisentestivarasto.example.com', 'Isokatu 32', '90100', 'Oulu', 14],
                ['Huolla trukki', 'Lumityöt pihalla', 'Korjaa lastauslaiturin tasoitin']],
            'Itä-Suomen Malliasunnot Oy' => [['Pekka Malli', 'talous@itasuomenmalliasunnot.example.com', 'Puijonkatu 20', '70100', 'Kuopio', 21],
                ['Tyhjennä rasvanerotin', 'Vaihda kulunvalvonnan kortinlukija', 'Puhdista ilmanvaihtokanavat', 'Vaihda WC:n poistoilmapuhallin']],
        ];

        // Seeding again never duplicates the demo data: a table that already has
        // requests only gets the locations added to demo requests that lack one.
        $customerIds = $this->seedCustomers($customers);

        if (ServiceRequest::query()->exists()) {
            foreach ($customerIds as $titles) {
                ServiceRequest::query()->whereIn('title', $titles['requests'])->whereNull('customer_id')->update(['customer_id' => $titles['id']]);
            }

            foreach ($requests as [$title, , , , , $location]) {
                if ($location !== null) {
                    ServiceRequest::query()
                        ->where('title', $title)
                        ->whereNull('latitude')
                        ->update($this->locationFields($location));
                }
            }

            return;
        }

        foreach ($requests as $index => [$title, $description, $priority, $status, $dueInDays, $location]) {
            $request = new ServiceRequest([
                'customer_id' => collect($customerIds)->first(fn ($customer) => in_array($title, $customer['requests'], true))['id'] ?? null,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'due_date' => today()->addDays($dueInDays),
                ...($location !== null ? $this->locationFields($location) : []),
            ]);

            // Older requests are spread over the past weeks.
            $request->created_at = now()->subDays($createdDaysAgo[$title] ?? 10 + $index * 2)->subHours($index);
            $request->updated_at = $request->created_at;
            $request->save();
        }
    }

    /**
     * @param  array{string, string, string, float, float}  $location
     * @return array<string, string|float>
     */
    private function locationFields(array $location): array
    {
        [$streetAddress, $postalCode, $city, $latitude, $longitude] = $location;

        return [
            'street_address' => $streetAddress,
            'postal_code' => $postalCode,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Creates the demo customers that don't exist yet.
     *
     * @param  array<string, array{array{string, string, string, string, string, int}, list<string>}>  $customers
     * @return list<array{id: int, requests: list<string>}>
     */
    private function seedCustomers(array $customers): array
    {
        $ids = [];

        foreach ($customers as $name => [[$contact, $email, $street, $postalCode, $city, $terms], $requests]) {
            $customer = Customer::firstOrCreate(['name' => $name], [
                'contact_person' => $contact,
                'email' => $email,
                'billing_street_address' => $street,
                'billing_postal_code' => $postalCode,
                'billing_city' => $city,
                'payment_terms_days' => $terms,
            ]);
            $ids[] = ['id' => $customer->id, 'requests' => $requests];
        }

        return $ids;
    }
}
