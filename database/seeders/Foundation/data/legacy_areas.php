<?php

/*
| v1 areas (tbl_area) → location paths [division, district, thana?, area?] (docs/11 §4.1, legacy
| seed spec L4). Missing thana / area nodes are created by LocationSeeder. null = no location
| ("Others"). Placements of neighbourhoods are best guesses: REVIEW with SOC before go-live.
*/

$dhaka = ['Dhaka', 'Dhaka'];

return [
    1 => $dhaka,
    2 => null,
    3 => ['Dhaka', 'Gazipur', 'Tongi West'],
    4 => [...$dhaka, 'Uttar Khan'],
    5 => [...$dhaka, 'Dakshinkhan'],
    6 => [...$dhaka, 'Khilkhet'],
    7 => [...$dhaka, 'Dakshinkhan', 'Kawla'],
    8 => [...$dhaka, 'Vatara', 'Bashundhara R/A'],
    9 => ['Dhaka', 'Narayanganj', 'Rupganj', 'Purbachal New Town'],
    10 => [...$dhaka, 'Vatara', 'Solmaid'],
    11 => [...$dhaka, 'Vatara', 'Sunvalley & Shodesh'],
    12 => [...$dhaka, 'Badda', 'Satarkul'],
    13 => [...$dhaka, 'Vatara'],
    14 => [...$dhaka, 'Vatara', 'Khilbarirtek'],
    15 => [...$dhaka, 'Badda', 'North Badda'],
    16 => [...$dhaka, 'Badda', 'Middle Badda'],
    17 => [...$dhaka, 'Badda', 'Aftabnagar'],
    18 => [...$dhaka, 'Rampura', 'Banasree'],
    19 => [...$dhaka, 'Mugda'],
    20 => [...$dhaka, 'Mugda', 'Green Model Town'],
    21 => [...$dhaka, 'Uttara West', 'Uttara Model Town'],
    22 => [...$dhaka, 'Turag', 'Bounia'],
    23 => [...$dhaka, 'Turag', 'Ranavola'],
    24 => [...$dhaka, 'Savar', 'Ashulia'],
    25 => [...$dhaka, 'Khilkhet', 'Nekaton'],
    26 => [...$dhaka, 'Mirpur'],
    27 => [...$dhaka, 'Gulshan', 'Kalachandpur'],
    28 => ['Dhaka', 'Narayanganj'],
    29 => [...$dhaka, 'Dhanmondi'],
    30 => [...$dhaka, 'Gulshan', 'Niketan'],
    31 => [...$dhaka, 'Savar'],
    32 => ['Khulna', 'Kushtia'],
    33 => [...$dhaka, 'Cantonment', 'Manikdi'],
    34 => [...$dhaka, 'Motijheel', 'Purana Paltan'],
    35 => ['Mymensingh', 'Mymensingh'],
    36 => ['Khulna', 'Chuadanga'],
    37 => ['Dhaka', 'Tangail'],
    38 => [...$dhaka, 'Gulshan', 'Gulshan-2'],
    39 => [...$dhaka, 'Gulshan', 'Gulshan-1'],
    40 => [...$dhaka, 'Tejgaon'],
    41 => [...$dhaka, 'Banani'],
    42 => [...$dhaka, 'Mohammadpur'],
    43 => [...$dhaka, 'Adabor', 'Shyamoli'],
    44 => ['Barishal', 'Pirojpur'],
    45 => ['Dhaka', 'Madaripur'],
    46 => ['Rajshahi', 'Pabna'],
    47 => ['Chattogram', 'Noakhali'],
    48 => ['Dhaka', 'Faridpur'],
    49 => ['Rajshahi', 'Sirajganj'],
    50 => [...$dhaka, 'Turag', 'Nolbhog'],
];
