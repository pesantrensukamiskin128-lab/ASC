<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradeSchemaSeeder extends Seeder
{
    public function run(): void
    {
        $schemaId = DB::table('grade_schemas')->insertGetId([
            'name'       => 'Standar',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $grades = [
            ['min' => 85, 'max' => 100, 'letter' => 'A',  'point' => 4.00],
            ['min' => 80, 'max' => 84.99, 'letter' => 'A-', 'point' => 3.75],
            ['min' => 75, 'max' => 79.99, 'letter' => 'B+', 'point' => 3.50],
            ['min' => 70, 'max' => 74.99, 'letter' => 'B',  'point' => 3.00],
            ['min' => 65, 'max' => 69.99, 'letter' => 'B-', 'point' => 2.75],
            ['min' => 60, 'max' => 64.99, 'letter' => 'C+', 'point' => 2.50],
            ['min' => 55, 'max' => 59.99, 'letter' => 'C',  'point' => 2.00],
            ['min' => 40, 'max' => 54.99, 'letter' => 'D',  'point' => 1.00],
            ['min' => 0,  'max' => 39.99, 'letter' => 'E',  'point' => 0.00],
        ];

        foreach ($grades as $i => $g) {
            DB::table('grade_schema_details')->insert([
                'grade_schema_id' => $schemaId,
                'min_score'       => $g['min'],
                'max_score'       => $g['max'],
                'letter'          => $g['letter'],
                'grade_point'     => $g['point'],
                'order'           => $i + 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
