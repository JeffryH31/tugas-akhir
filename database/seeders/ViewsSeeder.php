<?php

namespace Database\Seeders;

use App\Models\View;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class ViewsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sasya = $this->demoUser('admin@example.com');
        $dian = $this->demoUser('christopher@example.com');
        $budi = $this->demoUser('marvel@example.com');

        View::create(['task_list_id' => $this->demoTaskList('Inventory Module')->id, 'user_id' => $sasya->id, 'name' => 'Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['task_list_id' => $this->demoTaskList('Inventory Module')->id, 'user_id' => $sasya->id, 'name' => 'Gantt', 'type' => 'gantt', 'position' => 1]);
        View::create(['space_id' => $this->demoSpace('Manufacturing')->id, 'user_id' => $sasya->id, 'name' => 'All MFG Tasks', 'type' => 'list', 'is_default' => true, 'position' => 0]);
        View::create(['space_id' => $this->demoSpace('B2B')->id, 'user_id' => $sasya->id, 'name' => 'B2B Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['space_id' => $this->demoSpace('B2C')->id, 'user_id' => $dian->id, 'name' => 'B2C Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['task_list_id' => $this->demoTaskList('Checkout & Payment')->id, 'user_id' => $budi->id, 'name' => 'Payment Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
    }
}
