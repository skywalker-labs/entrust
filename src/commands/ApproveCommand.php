<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ApproveCommand extends Command
{
    protected $signature = 'skywalker:approve {id? : The ID of the request} {--list : List pending requests} {--reject : Reject the request}';
    protected $description = 'Approve or Reject access requests';

    public function handle(): void
    {
        if ($this->option('list') || !$this->argument('id')) {
            $this->listRequests();
            return;
        }

        $id = $this->argument('id');
        $request = DB::table('skywalker_access_requests')->where('id', $id)->first();

        if (!$request) {
            $this->error("Request not found: {$id}");
            return;
        }

        if ($request->status !== 'pending') {
            $this->error("Request #{$id} is already processed ({$request->status}).");
            return;
        }

        if ($this->option('reject')) {
            $this->processRequest($request, 'rejected');
            return;
        }

        $this->processRequest($request, 'approved');
    }

    protected function listRequests(): void
    {
        $requests = DB::table('skywalker_access_requests')->where('status', 'pending')->get();

        if ($requests->isEmpty()) {
            $this->info("No pending access requests.");
            return;
        }

        $this->info("Pending Access Requests:");
        $headers = ['ID', 'User ID', 'Type', 'Item', 'Reason', 'Date'];
        $data = [];

        foreach ($requests as $req) {
            $data[] = [$req->id, $req->user_id, $req->type, $req->requested_item, $req->reason, $req->created_at];
        }

        $this->table($headers, $data);
    }

    protected function processRequest($request, string $status): void
    {
        $userModel = Config::get('auth.providers.users.model');
        $user = $userModel::find($request->user_id);

        if (!$user) {
            $this->error("User not found for request #{$request->id}");
            return;
        }

        DB::transaction(function () use ($request, $user, $status) {
            DB::table('skywalker_access_requests')->where('id', $request->id)->update([
                'status' => $status,
                'updated_at' => \Carbon\Carbon::now(),
            ]);

            if ($status === 'approved') {
                if ($request->type === 'role') {
                    $user->attachRole($request->requested_item);
                } else {
                    $user->attachPermission($request->requested_item);
                }
            }
        });

        $this->info("Request #{$request->id} has been {$status}.");
    }
}
