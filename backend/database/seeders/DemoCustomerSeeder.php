<?php

namespace Database\Seeders;

use App\Models\BdDistrict;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Store;
use Database\Seeders\Concerns\BackdatesTimestamps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 100 customers with Bangladeshi names/phones, each with a real address
 * tied to the divisions/districts BdLocationSeeder already seeded —
 * weighted toward Dhaka/Chattogram the way an actual customer base would
 * skew. Most are guest-checkout-only (password null, per Customer's own
 * doc comment); a minority are registered accounts.
 */
class DemoCustomerSeeder extends Seeder
{
    use BackdatesTimestamps;

    private const MALE_FIRST_NAMES = [
        'Mohammad Rahim', 'Karim', 'Jahangir', 'Habibur', 'Mizanur', 'Shakib', 'Tanvir', 'Arif', 'Faisal', 'Rafiqul',
        'Shahriar', 'Imran', 'Nayeem', 'Rakibul', 'Zahid', 'Anisur', 'Rezaul', 'Shamim', 'Kamrul', 'Masud',
    ];

    private const FEMALE_FIRST_NAMES = [
        'Fatema', 'Rashida', 'Nasrin', 'Salma', 'Farzana', 'Shirin', 'Ayesha', 'Nusrat', 'Taslima', 'Rehana',
        'Sultana', 'Jasmin', 'Marium', 'Rupa', 'Shabnam', 'Ruma', 'Lubna', 'Sabina', 'Afsana', 'Hosne Ara',
    ];

    private const LAST_NAMES = [
        'Islam', 'Rahman', 'Hossain', 'Ahmed', 'Chowdhury', 'Khan', 'Uddin', 'Akter', 'Begum', 'Talukder',
        'Sarkar', 'Molla', 'Bhuiyan', 'Mia', 'Haque', 'Kabir', 'Alam', 'Siddique', 'Hasan', 'Miah',
    ];

    public function run(): void
    {
        $store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();

        // Weighted so the two biggest metros dominate, like a real customer base.
        $districtWeights = [
            'DHAKA' => 6, 'GAZIPUR' => 2, 'NARAYANGANJ' => 2,
            'CHATTOGRAM' => 4, 'COXSBAZAR' => 1, 'CUMILLA' => 2,
            'RAJSHAHI' => 1, 'BOGURA' => 1, 'KHULNA' => 1, 'JASHORE' => 1,
            'BARISHAL' => 1, 'BHOLA' => 1, 'SYLHET' => 2, 'MOULVIBAZAR' => 1,
            'RANGPUR' => 1, 'DINAJPUR' => 1, 'MYMENSINGH' => 1, 'JAMALPUR' => 1,
        ];

        $districts = BdDistrict::whereIn('code', array_keys($districtWeights))->get()->keyBy('code');
        $weightedPool = [];

        foreach ($districtWeights as $code => $weight) {
            if (! $districts->has($code)) {
                continue;
            }

            for ($i = 0; $i < $weight; $i++) {
                $weightedPool[] = $districts[$code];
            }
        }

        $usedEmails = [];

        for ($i = 1; $i <= 100; $i++) {
            $isMale = random_int(0, 1) === 0;
            $first = $isMale
                ? self::MALE_FIRST_NAMES[array_rand(self::MALE_FIRST_NAMES)]
                : self::FEMALE_FIRST_NAMES[array_rand(self::FEMALE_FIRST_NAMES)];
            $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $name = "{$first} {$last}";

            $emailBase = strtolower(str_replace(' ', '.', $first)).'.'.strtolower($last);
            $email = "{$emailBase}{$i}@example.test";
            while (in_array($email, $usedEmails, true)) {
                $email = "{$emailBase}{$i}".random_int(10, 99).'@example.test';
            }
            $usedEmails[] = $email;

            $isRegistered = random_int(1, 100) <= 20;
            $registeredAt = now()->subDays(random_int(1, 300));

            $customer = Customer::create([
                'store_id' => $store->id,
                'name' => $name,
                'email' => $email,
                'phone' => '01'.random_int(3, 9).str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'password' => $isRegistered ? Hash::make('password') : null,
                'status' => 'active',
            ]);
            $this->backdate($customer, $registeredAt);

            $homeDistrict = $weightedPool[array_rand($weightedPool)];

            CustomerAddress::create([
                'customer_id' => $customer->id,
                'label' => 'Home',
                'recipient_name' => $name,
                'phone' => $customer->phone,
                'address_line' => sprintf('House #%d, Road #%d, %s', random_int(1, 40), random_int(1, 25), $homeDistrict->name_en),
                'bd_division_id' => $homeDistrict->bd_division_id,
                'bd_district_id' => $homeDistrict->id,
                'is_default' => true,
            ]);

            // A fifth of customers also have a second, non-default address (e.g. office).
            if (random_int(1, 100) <= 20) {
                $officeDistrict = $weightedPool[array_rand($weightedPool)];

                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'label' => 'Office',
                    'recipient_name' => $name,
                    'phone' => $customer->phone,
                    'address_line' => sprintf('House #%d, Road #%d, %s', random_int(1, 40), random_int(1, 25), $officeDistrict->name_en),
                    'bd_division_id' => $officeDistrict->bd_division_id,
                    'bd_district_id' => $officeDistrict->id,
                    'is_default' => false,
                ]);
            }
        }
    }
}
