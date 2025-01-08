<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class PdfExtractorService
{
    private GroqService $groq;

    public function __construct()
    {
        $this->groq = app(GroqService::class);
    }

    public function extractInformation(string $pdfPath): array
    {
        $pdfText = (new Pdf)
            ->setPdf($pdfPath)
            ->text();

        try {
            $response = $this->groq->chat([
                [
                    'role' => 'system',
                    'content' => <<<'EOT'
Extract information from Malaysian resumes and documents following these rules:

1. Personal Information:
- Name: Extract full name
- Phone Number:
  * Prioritize Malaysian phone numbers (formats: +60xx, 01x-xxxxxxx, 01x xxxxxxx)
  * If no Malaysian number found, extract other country numbers
  * Always format with international code (e.g., +60, +65, +44)
- Email: Extract email address
- Format as:
{
    "name": string,
    "email": string,
    "phone_number": string
}

2. Social Media:
- Extract social media profiles
- Must be one of: 'linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'
- Format as:
[{
    "type": "social_media",
    "data": {
        "social_media": string,  // One of the allowed types
        "username": string,
        "url": string
    }
}]

3. Qualifications:
- Only include formal academic qualifications from recognized institutions
- Qualification must be ONE of: 'Diploma', 'Bachelor', 'Master', 'PhD', 'Others'
- Ignore all online courses, certificates, and non-academic qualifications
- For qualifications not fitting the above categories exactly, use 'Others'
- Include only the highest qualification from each institution

Format qualifications as:
[{
    "type": "qualification",
    "data": {
        "university": string,  // Full institution name
        "qualification": string,  // MUST be one of: Diploma, Bachelor, Master, PhD, Others
        "major": string,
        "gpa": string,  // If not available, use null
        "from": YYYY-MM-DD,  // If only year available, use YYYY-01-01, if not available, use null
        "to": YYYY-MM-DD or null if ongoing
    }
}]

Return only valid JSON with personal_info, social_media, and qualifications arrays.
Maintain chronological order of qualifications (newest first).
EOT
                ],
                [
                    'role' => 'user',
                    'content' => $pdfText,
                ],
            ], [
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response['choices'][0]['message']['content'], true);

            if (isset($result['qualifications'])) {
                foreach ($result['qualifications'] as &$qual) {
                    if (! in_array($qual['data']['qualification'], ['Diploma', 'Bachelor', 'Master', 'PhD', 'Others'])) {
                        $qual['data']['qualification'] = 'Others';
                    }
                }
            }

            if (isset($result['social_media'])) {
                foreach ($result['social_media'] as &$social) {
                    if (! in_array($social['data']['social_media'], ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'])) {
                        $social['data']['social_media'] = 'others';
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Groq extraction failed: '.$e->getMessage());

            return [
                'personal_info' => [],
                'social_media' => [],
                'qualifications' => [],
            ];
        }
    }
}
