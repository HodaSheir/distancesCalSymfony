<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use League\Csv\Writer;

#[AsCommand(
    name: 'CalculateDistances',
    description: 'Calculate distances and store in CSV',
    hidden: false,
    aliases: ['app:calculate-distances']
)]
class CalculateDistancesCommand extends Command
{

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Geolocation API endpoint (PositionStack)
        $apiEndpoint = 'http://api.positionstack.com/v1/forward';
        $apiKey = '9eb61b6aa98a57d4201f19b0253c92aa';

        // Addresses array
        $addresses = [
            'Deldenerstraat 70, 7551AH Hengelo, The Netherlands',
            '46/1 Office no 1 Ground Floor, Dada House, Inside dada silk mills compound, Udhana Main Rd, near Chhaydo Hospital, Surat, 394210, India',
            'Weena 505, 3013 AL Rotterdam, The Netherlands',
            '221B Baker St., London, United Kingdom',
            '1600 Pennsylvania Avenue, Washington, D.C., USA',
            '350 Fifth Avenue, New York City, NY 10118, USA',
            'Saint Martha House, 00120 Citta del Vaticano, Vatican City',
            '5225 Figueroa Mountain Road, Los Olivos, Calif. 93441, USA',
        ];

        // Initialize Guzzle client
        $geolocationApi = new Client();

        //get Adchieve headquarters longitude , latitude 
        $address = "Adchieve HQ - Sint Janssingel 92, 5211 DA 's-Hertogenbosch, The Netherlands";
        $headquarterData  = $geolocationApi->get($apiEndpoint, [
            'query' => [
                'access_key' => $apiKey,
                'query' => $address,
            ],
        ]);
        $headquarterData = json_decode($headquarterData->getBody(), true);
        // Extract adchieve latitude and longitude from API response
        $adchLatitude = $headquarterData['data'][0]['latitude'];
        $adchLongitude = $headquarterData['data'][0]['longitude'];

        foreach ($addresses as $address) {
            // Make API request to get coordinates
            $response = $geolocationApi->get($apiEndpoint, [
                'query' => [
                    'access_key' => $apiKey,
                    'query' => $address,
                ],
            ]);
            $data = json_decode($response->getBody(), true);
    
            // Extract latitude , longitude , name and label from API response
            $latitude = $data['data'][0]['latitude'];
            $longitude = $data['data'][0]['longitude'];
            $name = $data['data'][0]['name'];
            $label = $data['data'][0]['label'];

            // Calculate distance using Haversine formula
            $distance = $this->calculateDistance($adchLatitude, $adchLongitude, $latitude, $longitude);
    
            $distances[] = [
                'distance' => $distance,
                'name' => $name,
                'label' => $label,
            ];
        }
        //sort distances
        usort($distances, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Write sorted results to CSV
        $csv = Writer::createFromPath('distances.csv', 'w+');
        $csv->insertOne(['Sortnumber', 'Distance', 'Name', 'Address']);
        foreach ($distances as $index => $distance) {
            $csv->insertOne([$index + 1, sprintf('%.2f km', $distance['distance']), $distance['name'], $distance['label']]);
        }

        $io->success('Distances calculated and written to distances.csv');

        return Command::SUCCESS;
    }

    //Haversine formula
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radius of the Earth in kilometers

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;
        return $distance;
    }

}
