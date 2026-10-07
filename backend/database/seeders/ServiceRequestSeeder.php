<?php

namespace Database\Seeders;

use App\Models\ServiceRequest;
use Illuminate\Database\Seeder;

class ServiceRequestSeeder extends Seeder
{
    /**
     * Seed realistic demo service requests.
     *
     * Due dates are relative to today so the demo always has upcoming,
     * due-soon and overdue requests.
     */
    public function run(): void
    {
        // Seed only an empty table, so seeding again never duplicates the demo data.
        if (ServiceRequest::query()->exists()) {
            return;
        }

        $requests = [
            ['Vaihda ilmansuodatin', 'Vaihda varaston ilmanvaihtokoneen ilmansuodatin.', 'high', 'open', 8],
            ['Tarkasta varaston nosto-ovi', 'Nosto-ovi pysähtyy puoliväliin. Tarkasta jouset ja turvatunnistimet.', 'high', 'in_progress', 2],
            ['Vaihda toimiston loisteputket', 'Toisen kerroksen neuvotteluhuoneen kaksi loisteputkea vilkkuu.', 'low', 'completed', -5],
            ['Korjaa vuotava hana', 'Taukotilan keittiön hana tiputtaa jatkuvasti.', 'normal', 'open', -3],
            ['Huolla trukki', 'Trukin määräaikaishuolto: öljyt, renkaat ja jarrut.', 'normal', 'open', 14],
            ['Tyhjennä rasvanerotin', 'Henkilöstöravintolan rasvanerottimen tyhjennys ja tarkastus.', 'high', 'open', -1],
            ['Testaa paloilmoitinjärjestelmä', 'Kuukausittainen paloilmoitinjärjestelmän koestus ja kirjaus.', 'high', 'completed', -10],
            ['Lumityöt pihalla', 'Lastauslaiturin edustan auraus ja hiekoitus ennen aamuvuoroa.', 'normal', 'completed', -2],
            ['Vaihda kulunvalvonnan kortinlukija', 'Pääoven kortinlukija ei tunnista kaikkia kulkukortteja.', 'normal', 'in_progress', 5],
            ['Puhdista ilmanvaihtokanavat', 'Toimistosiiven ilmanvaihtokanavien nuohous ja puhdistus.', 'low', 'open', 30],
            ['Korjaa lastauslaiturin tasoitin', 'Laiturin 3 tasoitin ei nouse täysin ylös.', 'high', 'open', -6],
            ['Vaihda WC:n poistoilmapuhallin', 'Alakerran WC:n poistoilmapuhallin pitää kovaa ääntä.', 'low', 'open', 21],
            ['Tarkasta ensiapukaapit', 'Täydennä ensiapukaappien tarvikkeet ja tarkasta vanhenemispäivät.', 'normal', 'completed', -14],
            ['Maalaa varaston lattiamerkinnät', 'Kulkuväylien lattiamerkinnät ovat kuluneet näkymättömiin.', 'low', 'in_progress', 12],
            ['Säädä toimiston lämmitys', 'Avotoimiston lämpötila on aamuisin alle 19 astetta.', 'normal', 'open', 4],
        ];

        foreach ($requests as [$title, $description, $priority, $status, $dueInDays]) {
            ServiceRequest::create([
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'due_date' => today()->addDays($dueInDays),
            ]);
        }
    }
}
