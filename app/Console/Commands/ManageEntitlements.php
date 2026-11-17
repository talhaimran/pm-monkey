<?php

namespace App\Console\Commands;

use App\Enums\Entitlement;
use App\Models\Workspace;
use App\Services\Entitlements;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pm:entitlements {workspace : The workspace address (slug)} {--set=* : key=value, as an override} {--unset=* : key; no row means unlimited} {--list : Show every key}')]
#[Description('List or change a workspace\'s plan limits (spec 7.0c)')]
class ManageEntitlements extends Command
{
    public function handle(Entitlements $entitlements): int
    {
        $workspace = Workspace::firstWhere('slug', $this->argument('workspace'));
        if (! $workspace) {
            return $this->refuse("No workspace with the address \"{$this->argument('workspace')}\".");
        }

        // Check everything first, so a bad entry changes nothing.
        $sets = [];
        foreach ($this->option('set') as $pair) {
            if (! str_contains($pair, '=')) {
                return $this->refuse('Use --set=key=value.');
            }
            [$name, $value] = explode('=', $pair, 2);
            if (! $key = $this->key($name)) {
                return self::FAILURE;
            }
            if ($error = $key->normalize($value)[1]) {
                return $this->refuse($error);
            }
            $sets[] = [$key, $value];
        }
        $unsets = [];
        foreach ($this->option('unset') as $name) {
            if (! $key = $this->key($name)) {
                return self::FAILURE;
            }
            $unsets[] = $key;
        }

        foreach ($sets as [$key, $value]) {
            $entitlements->set($workspace, $key, $value, 'override');
            $this->line("{$key->value} = {$entitlements->get($workspace, $key)}");
        }
        foreach ($unsets as $key) {
            $entitlements->unset($workspace, $key);
            $this->line("{$key->value} = unlimited");
        }

        if ($this->option('list') || (! $sets && ! $unsets)) {
            $rows = $workspace->entitlements()->get()->keyBy('key');
            $this->table(['Key', 'Value', 'Source', 'Enforced in step', 'Meaning'], array_map(fn (Entitlement $key) => [
                $key->value, $rows[$key->value]->value ?? 'unlimited', $rows[$key->value]->source ?? '', $key->enforcedInStep() ?? '–', $key->description(),
            ], Entitlement::cases()));
        }

        return self::SUCCESS;
    }

    private function key(string $name): ?Entitlement
    {
        $key = Entitlement::tryFrom(trim($name));
        if (! $key) {
            $this->error("Unknown entitlement \"{$name}\". Valid keys: ".implode(', ', array_column(Entitlement::cases(), 'value')).'.');
        }

        return $key;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
