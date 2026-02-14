<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UIScaffoldCommand extends Command
{
    protected $signature = 'skywalker:ui';
    protected $description = 'Scaffold a basic Role & Permission management UI';

    public function handle(): void
    {
        $this->info("Scaffolding management UI...");

        // 1. Create Controller
        $controllerPath = app_path('Http/Controllers/Skywalker/RoleController.php');
        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $this->getControllerContent());

        // 2. Create Views
        $viewPath = resource_path('views/skywalker');
        File::ensureDirectoryExists($viewPath);
        File::put($viewPath . '/index.blade.php', $this->getIndexViewContent());

        $this->info("UI Scaffold completed!");
        $this->comment("1. Added Controller at: {$controllerPath}");
        $this->comment("2. Added Views at: {$viewPath}");
        $this->comment("3. Don't forget to register routes in your web.php!");
    }

    protected function getControllerContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Controllers\Skywalker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class RoleController extends Controller
{
    public function index()
    {
        $roleClass = Config::get('entrust.role');
        $roles = $roleClass::with('perms')->get();
        return view('skywalker.index', compact('roles'));
    }
}
PHP;
    }

    protected function getIndexViewContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Skywalker Roles</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white rounded shadow p-6">
        <h1 class="text-2xl font-bold mb-4">Roles & Permissions</h1>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 border">Role</th>
                    <th class="p-2 border">Permissions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                <tr>
                    <td class="p-2 border font-semibold">{{ $role->name }}</td>
                    <td class="p-2 border">
                        @foreach($role->perms as $perm)
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">{{ $perm->name }}</span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
HTML;
    }
}
