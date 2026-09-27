<?php

namespace Database\Seeders;

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use Illuminate\Database\Seeder;

/**
 * Seeds the 8 official divisions of Bangladesh plus a small, deliberately
 * representative subset of well-known districts/upazilas per division —
 * enough to exercise the address hierarchy end to end (dropdowns, address
 * forms, warehouse locations) without hand-typing all 64 districts and
 * 490+ upazilas, which risks silently encoding wrong data. The full,
 * authoritative dataset should be loaded via a future admin data-import
 * job (spec section 10: "create import/update mechanisms for
 * administrative data"), not hardcoded here.
 */
class BdLocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'DHK' => [
                'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা',
                'districts' => [
                    'DHAKA' => ['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'upazilas' => [
                        ['name_en' => 'Savar', 'name_bn' => 'সাভার', 'code' => 'SAVAR'],
                        ['name_en' => 'Dhamrai', 'name_bn' => 'ধামরাই', 'code' => 'DHAMRAI'],
                    ]],
                    'GAZIPUR' => ['name_en' => 'Gazipur', 'name_bn' => 'গাজীপুর', 'upazilas' => [
                        ['name_en' => 'Gazipur Sadar', 'name_bn' => 'গাজীপুর সদর', 'code' => 'GAZIPUR_SADAR'],
                    ]],
                    'NARAYANGANJ' => ['name_en' => 'Narayanganj', 'name_bn' => 'নারায়ণগঞ্জ', 'upazilas' => [
                        ['name_en' => 'Narayanganj Sadar', 'name_bn' => 'নারায়ণগঞ্জ সদর', 'code' => 'NARAYANGANJ_SADAR'],
                    ]],
                ],
            ],
            'CTG' => [
                'name_en' => 'Chattogram', 'name_bn' => 'চট্টগ্রাম',
                'districts' => [
                    'CHATTOGRAM' => ['name_en' => 'Chattogram', 'name_bn' => 'চট্টগ্রাম', 'upazilas' => [
                        ['name_en' => 'Patiya', 'name_bn' => 'পটিয়া', 'code' => 'PATIYA'],
                    ]],
                    'COXSBAZAR' => ['name_en' => "Cox's Bazar", 'name_bn' => 'কক্সবাজার', 'upazilas' => [
                        ['name_en' => "Cox's Bazar Sadar", 'name_bn' => 'কক্সবাজার সদর', 'code' => 'COXSBAZAR_SADAR'],
                        ['name_en' => 'Teknaf', 'name_bn' => 'টেকনাফ', 'code' => 'TEKNAF'],
                    ]],
                    'CUMILLA' => ['name_en' => 'Cumilla', 'name_bn' => 'কুমিল্লা', 'upazilas' => [
                        ['name_en' => 'Cumilla Sadar', 'name_bn' => 'কুমিল্লা সদর', 'code' => 'CUMILLA_SADAR'],
                    ]],
                ],
            ],
            'RAJ' => [
                'name_en' => 'Rajshahi', 'name_bn' => 'রাজশাহী',
                'districts' => [
                    'RAJSHAHI' => ['name_en' => 'Rajshahi', 'name_bn' => 'রাজশাহী', 'upazilas' => [
                        ['name_en' => 'Rajshahi Sadar', 'name_bn' => 'রাজশাহী সদর', 'code' => 'RAJSHAHI_SADAR'],
                    ]],
                    'BOGURA' => ['name_en' => 'Bogura', 'name_bn' => 'বগুড়া', 'upazilas' => [
                        ['name_en' => 'Bogura Sadar', 'name_bn' => 'বগুড়া সদর', 'code' => 'BOGURA_SADAR'],
                    ]],
                ],
            ],
            'KHL' => [
                'name_en' => 'Khulna', 'name_bn' => 'খুলনা',
                'districts' => [
                    'KHULNA' => ['name_en' => 'Khulna', 'name_bn' => 'খুলনা', 'upazilas' => [
                        ['name_en' => 'Khulna Sadar', 'name_bn' => 'খুলনা সদর', 'code' => 'KHULNA_SADAR'],
                    ]],
                    'JASHORE' => ['name_en' => 'Jashore', 'name_bn' => 'যশোর', 'upazilas' => [
                        ['name_en' => 'Jashore Sadar', 'name_bn' => 'যশোর সদর', 'code' => 'JASHORE_SADAR'],
                    ]],
                ],
            ],
            'BAR' => [
                'name_en' => 'Barishal', 'name_bn' => 'বরিশাল',
                'districts' => [
                    'BARISHAL' => ['name_en' => 'Barishal', 'name_bn' => 'বরিশাল', 'upazilas' => [
                        ['name_en' => 'Barishal Sadar', 'name_bn' => 'বরিশাল সদর', 'code' => 'BARISHAL_SADAR'],
                    ]],
                    'BHOLA' => ['name_en' => 'Bhola', 'name_bn' => 'ভোলা', 'upazilas' => [
                        ['name_en' => 'Bhola Sadar', 'name_bn' => 'ভোলা সদর', 'code' => 'BHOLA_SADAR'],
                    ]],
                ],
            ],
            'SYL' => [
                'name_en' => 'Sylhet', 'name_bn' => 'সিলেট',
                'districts' => [
                    'SYLHET' => ['name_en' => 'Sylhet', 'name_bn' => 'সিলেট', 'upazilas' => [
                        ['name_en' => 'Sylhet Sadar', 'name_bn' => 'সিলেট সদর', 'code' => 'SYLHET_SADAR'],
                    ]],
                    'MOULVIBAZAR' => ['name_en' => 'Moulvibazar', 'name_bn' => 'মৌলভীবাজার', 'upazilas' => [
                        ['name_en' => 'Moulvibazar Sadar', 'name_bn' => 'মৌলভীবাজার সদর', 'code' => 'MOULVIBAZAR_SADAR'],
                    ]],
                ],
            ],
            'RNG' => [
                'name_en' => 'Rangpur', 'name_bn' => 'রংপুর',
                'districts' => [
                    'RANGPUR' => ['name_en' => 'Rangpur', 'name_bn' => 'রংপুর', 'upazilas' => [
                        ['name_en' => 'Rangpur Sadar', 'name_bn' => 'রংপুর সদর', 'code' => 'RANGPUR_SADAR'],
                    ]],
                    'DINAJPUR' => ['name_en' => 'Dinajpur', 'name_bn' => 'দিনাজপুর', 'upazilas' => [
                        ['name_en' => 'Dinajpur Sadar', 'name_bn' => 'দিনাজপুর সদর', 'code' => 'DINAJPUR_SADAR'],
                    ]],
                ],
            ],
            'MYM' => [
                'name_en' => 'Mymensingh', 'name_bn' => 'ময়মনসিংহ',
                'districts' => [
                    'MYMENSINGH' => ['name_en' => 'Mymensingh', 'name_bn' => 'ময়মনসিংহ', 'upazilas' => [
                        ['name_en' => 'Mymensingh Sadar', 'name_bn' => 'ময়মনসিংহ সদর', 'code' => 'MYMENSINGH_SADAR'],
                    ]],
                    'JAMALPUR' => ['name_en' => 'Jamalpur', 'name_bn' => 'জামালপুর', 'upazilas' => [
                        ['name_en' => 'Jamalpur Sadar', 'name_bn' => 'জামালপুর সদর', 'code' => 'JAMALPUR_SADAR'],
                    ]],
                ],
            ],
        ];

        foreach ($data as $divisionCode => $division) {
            $divisionModel = BdDivision::updateOrCreate(
                ['code' => $divisionCode],
                ['name_en' => $division['name_en'], 'name_bn' => $division['name_bn']],
            );

            foreach ($division['districts'] as $districtCode => $district) {
                $districtModel = BdDistrict::updateOrCreate(
                    ['bd_division_id' => $divisionModel->id, 'code' => $districtCode],
                    ['name_en' => $district['name_en'], 'name_bn' => $district['name_bn']],
                );

                foreach ($district['upazilas'] as $upazila) {
                    BdUpazila::updateOrCreate(
                        ['bd_district_id' => $districtModel->id, 'code' => $upazila['code']],
                        ['name_en' => $upazila['name_en'], 'name_bn' => $upazila['name_bn']],
                    );
                }
            }
        }
    }
}
