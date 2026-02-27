<?php
namespace Database\Seeders\Static;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates or updates accounts with superadmin privileges.
     *
     * @return void
     */
    public function run(): void
    {
        $admins = [
            [
                'id' => 0,
                'login' => 'superadmin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'initials' => 'SA',
                'code' => '000000',
                'phone_number' => '0000000000',
                'email' => 'superadmin@example.com',
                'pin' => env('SUPERADMIN_PASSWORD', 'changeme'),
            ],
            // add additional superadmins here
        ];

        foreach ($admins as $data) {
            $user = User::updateOrCreate(
                ['login' => $data['login']],
                [
                    'id' => $data['id'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'initials' => $data['initials'],
                    'code' => $data['code'],
                    'phone_number' => $data['phone_number'],
                    'email' => $data['email'],
                    'pin' => Hash::make($data['pin']),
                ]
            );

            // assign role if the project uses spatie/permission
            if (method_exists($user, 'assignRole')) {
                $user->assignRole(0);
            }
        }
    }
}
