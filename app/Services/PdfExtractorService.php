<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class PdfExtractorService
{
    private $groq;

    public function __construct()
    {
        $this->groq = app(GroqService::class);
    }

    /**
     * Extract information from PDF using Groq
     */
    public function extractInformation(string $pdfPath): array
    {
        // Extract text from PDF
        $pdfText = (new Pdf)
            ->setPdf($pdfPath)
            ->text();

        try {
            $response = $this->groq->chat([
                [
                    'role' => 'system',
                    'content' => <<<EOT
Extract information from Malaysian resumes and documents following these rules:

1. Personal Information:
- Name: Extract full name
- Phone Number:
  * Prioritize Malaysian phone numbers (formats: +60xx, 01x-xxxxxxx, 01x xxxxxxx)
  * If no Malaysian number found, extract other country numbers
  * Always format with international code (e.g., +60, +65, +44)
- Email: Extract email address

2. Qualifications:
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
        "from": YYYY-MM-DD,  // If only year available, use YYYY-01-01
        "to": YYYY-MM-DD or null if ongoing
    }
}]

Return only valid JSON with personal_info and qualifications arrays.
Maintain chronological order of qualifications (newest first).
EOT
                ],
                [
                    'role' => 'user',
                    'content' => $pdfText
                ]
            ], [
                'response_format' => ['type' => 'json_object']
            ]);

            $result = json_decode($response['choices'][0]['message']['content'], true);

            // Additional validation for qualification types
            if (isset($result['qualifications'])) {
                foreach ($result['qualifications'] as &$qual) {
                    if (!in_array($qual['data']['qualification'], ['Diploma', 'Bachelor', 'Master', 'PhD', 'Others'])) {
                        $qual['data']['qualification'] = 'Others';
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Groq extraction failed: ' . $e->getMessage());
            return [
                'personal_info' => [],
                'qualifications' => []
            ];
        }
    }
}
