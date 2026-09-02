<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create(['name'=>'山田 太郎','email'=>'staff@sansun.test','password'=>'Staff123','role'=>'staff','kana'=>'ヤマダ タロウ']);
        User::create(['name'=>'佐藤 美咲','email'=>'teacher@sansun.test','password'=>'Teacher123','role'=>'teacher','kana'=>'サトウ ミサキ']);
        User::create(['name'=>'鈴木 健','email'=>'teacher2@sansun.test','password'=>'Teacher123','role'=>'teacher','kana'=>'スズキ ケン']);
        $this->call(AcademicSeeder::class);
    }
}
