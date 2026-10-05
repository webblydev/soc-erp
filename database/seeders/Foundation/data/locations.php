<?php

/*
| Bangladesh divisions → districts → thanas/upazilas (docs/01 §4). Thanas are seeded for
| Dhaka and Gazipur only. REVIEW against the official BBS list before go-live.
*/

return [
    'Barishal' => ['Barguna' => [], 'Barishal' => [], 'Bhola' => [], 'Jhalokati' => [], 'Patuakhali' => [], 'Pirojpur' => []],
    'Chattogram' => [
        'Bandarban' => [], 'Brahmanbaria' => [], 'Chandpur' => [], 'Chattogram' => [], "Cox's Bazar" => [], 'Cumilla' => [],
        'Feni' => [], 'Khagrachhari' => [], 'Lakshmipur' => [], 'Noakhali' => [], 'Rangamati' => [],
    ],
    'Dhaka' => [
        'Dhaka' => [
            'Adabor', 'Badda', 'Banani', 'Bangshal', 'Bhashantek', 'Bhatara', 'Biman Bandar', 'Cantonment', 'Chawkbazar',
            'Dakshinkhan', 'Darus Salam', 'Demra', 'Dhamrai', 'Dhanmondi', 'Dohar', 'Gendaria', 'Gulshan', 'Hatirjheel',
            'Hazaribagh', 'Jatrabari', 'Kadamtali', 'Kafrul', 'Kalabagan', 'Kamrangirchar', 'Keraniganj', 'Khilgaon',
            'Khilkhet', 'Kotwali', 'Lalbagh', 'Mirpur', 'Mohammadpur', 'Motijheel', 'Mugda', 'Nawabganj', 'New Market',
            'Pallabi', 'Paltan', 'Ramna', 'Rampura', 'Rupnagar', 'Sabujbagh', 'Savar', 'Shah Ali', 'Shahbagh',
            'Shahjahanpur', 'Sher-e-Bangla Nagar', 'Shyampur', 'Sutrapur', 'Tejgaon', 'Tejgaon Industrial Area', 'Turag',
            'Uttara East', 'Uttara West', 'Uttar Khan', 'Vatara', 'Wari',
        ],
        'Faridpur' => [], 'Gazipur' => [
            'Gazipur Sadar', 'Kaliakair', 'Kaliganj', 'Kapasia', 'Sreepur', 'Basan', 'Gacha', 'Joydebpur', 'Kashimpur',
            'Konabari', 'Pubail', 'Tongi East', 'Tongi West',
        ],
        'Gopalganj' => [], 'Kishoreganj' => [], 'Madaripur' => [], 'Manikganj' => [], 'Munshiganj' => [],
        'Narayanganj' => [], 'Narsingdi' => [], 'Rajbari' => [], 'Shariatpur' => [], 'Tangail' => [],
    ],
    'Khulna' => [
        'Bagerhat' => [], 'Chuadanga' => [], 'Jashore' => [], 'Jhenaidah' => [], 'Khulna' => [], 'Kushtia' => [],
        'Magura' => [], 'Meherpur' => [], 'Narail' => [], 'Satkhira' => [],
    ],
    'Mymensingh' => ['Jamalpur' => [], 'Mymensingh' => [], 'Netrokona' => [], 'Sherpur' => []],
    'Rajshahi' => [
        'Bogura' => [], 'Chapainawabganj' => [], 'Joypurhat' => [], 'Naogaon' => [], 'Natore' => [], 'Pabna' => [],
        'Rajshahi' => [], 'Sirajganj' => [],
    ],
    'Rangpur' => [
        'Dinajpur' => [], 'Gaibandha' => [], 'Kurigram' => [], 'Lalmonirhat' => [], 'Nilphamari' => [], 'Panchagarh' => [],
        'Rangpur' => [], 'Thakurgaon' => [],
    ],
    'Sylhet' => ['Habiganj' => [], 'Moulvibazar' => [], 'Sunamganj' => [], 'Sylhet' => []],
];
