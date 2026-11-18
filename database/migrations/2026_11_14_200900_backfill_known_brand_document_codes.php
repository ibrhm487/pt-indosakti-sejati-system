<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->knownBrandCodes() as $slug => $code) {
            DB::table('brands')->where('slug', $slug)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        // Keep canonical brand codes when rolling back; document numbers may already use them.
    }

    /**
     * @return array<string, string>
     */
    private function knownBrandCodes(): array
    {
        return [
            'milo' => 'MLO',
            'dancow' => 'DCW',
            'kitkat' => 'KKT',
            'bear-brand' => 'BBR',
        ];
    }
};
