<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'manager_id'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public static function ensureForManager(User $manager): self
    {
        $department = static::firstOrCreate(
            ['manager_id' => $manager->id],
            ['name' => $manager->name]
        );

        if ($department->name !== $manager->name) {
            $department->name = $manager->name;
            $department->save();
        }

        if ($manager->department_id !== $department->id) {
            $manager->department_id = $department->id;
            $manager->save();
        }

        return $department;
    }

    public static function syncFromManagers(): void
    {
        User::where('role', 'Trưởng phòng')->get()->each(function (User $manager) {
            static::ensureForManager($manager);
        });
    }
}
