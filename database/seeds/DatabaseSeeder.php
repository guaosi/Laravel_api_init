<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
        for($i=1;$i<=2;$i++){
            DB::table('users')->insert([
                'name' => 'guaosi'.$i,
                'password' => bcrypt('12345678'),
            ]);
            DB::table('admins')->insert([
                'name' => 'guaosi'.(122+$i),
                'password' => bcrypt('12345678'),
            ]);
        }
    }
}
