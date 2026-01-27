<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tiptap\Editor;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all candidates with working experiences
        $candidates = DB::table('candidates')
            ->whereNotNull('working_experiences')
            ->get();

        foreach ($candidates as $candidate) {
            $workExperiences = json_decode($candidate->working_experiences, true);

            if (! is_array($workExperiences)) {
                continue;
            }

            $modified = false;
            foreach ($workExperiences as &$experience) {
                // If responsibilities exists and is a string (HTML/plain text), convert to TipTap JSON
                if (isset($experience['responsibilities']) && is_string($experience['responsibilities']) && ! empty($experience['responsibilities'])) {
                    try {
                        $editor = new Editor;
                        $experience['responsibilities'] = $editor
                            ->setContent($experience['responsibilities'])
                            ->getDocument();
                        $modified = true;
                    } catch (\Exception $e) {
                        // If conversion fails, set to null
                        $experience['responsibilities'] = null;
                        $modified = true;
                    }
                } elseif (isset($experience['responsibilities']) && (empty($experience['responsibilities']) || $experience['responsibilities'] === '')) {
                    // Empty strings become null
                    $experience['responsibilities'] = null;
                    $modified = true;
                }
            }

            if ($modified) {
                DB::table('candidates')
                    ->where('id', $candidate->id)
                    ->update(['working_experiences' => json_encode($workExperiences)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversing TipTap JSON to HTML would require complex conversion
        // Not implemented - back up data before running this migration
    }
};
