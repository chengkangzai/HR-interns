<?php

namespace App\Services;

use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;
use Spatie\Tags\Tag;

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

        $skills = Tag::query()
            ->whereType('skills')
            ->pluck('name')
            ->toJson();

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
- Format as:
{
    "name": string,
    "email": string,
    "phone_number": string
}

2. Skills:
- Extract skills from multiple sources:
  a) Explicit skill sections/lists in the resume
  b) Technical skills mentioned in work experience descriptions
  c) Tools, technologies, and frameworks used in projects
  d) Programming languages and software mentioned anywhere in the document
- Look for skills in various formats:
  * Direct mentions (e.g., "Proficient in Python")
  * Project usage (e.g., "Developed React components")
  * Tool usage (e.g., "Used JIRA for project management")
  * Implied skills (e.g., "REST API development" implies API development skills)
- Match against the following existing skill tags if possible:
{$skills}
- If a skill doesn't match any existing tag, create a new one
- Normalize skill names (e.g., "Tailwind CSS" -> "TailwindCSS", "React.js" -> "React")
- Remove duplicates and combine skills from all sources
- Return as array of strings
- Format as: ["Laravel", "TailwindCSS", "Vue.js"]

3. Social Media:
- Extract social media profiles
- Must be one of: 'linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'
- The url must be in the full address. For example, 'https://google.com' instead of 'google.com'
- Format as:
[{
    "type": "social_media",
    "data": {
        "social_media": string,  // One of the allowed types
        "username": string,
        "url": string
    }
}]

4. Qualifications:
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

5. Work Experience:
- Extract all work experiences
- Employment type must be ONE of: 'Full_time', 'Part_time', 'Contract', 'Internship', 'Freelance', 'Other'
- If employment type is not explicitly stated, default to 'Full_time'
- Location format must be 'City, State/Country' (e.g., 'Johor Bahru, Johor', 'Bukit Jalil, Kuala Lumpur')
- For current positions, set end_date as null and is_current as true

Format work experience as:
[{
    "company": string,          // Company name
    "position": string,         // Job title
    "employment_type": string,  // MUST be one of the allowed types, default to 'Full_time' if not specified
    "location": string,         // City, State/Country format
    "start_date": YYYY-MM-DD,  // If only year available, use YYYY-01-01
    "end_date": YYYY-MM-DD,    // null if currently working
    "is_current": boolean,      // true if currently working
    "responsibilities": string  // Job description/responsibilities
}]

Return only valid JSON with personal_info, skills (array of strings), social_media, qualifications, and work_experience arrays.
Maintain chronological order of qualifications and work experience (newest first).

IMPORTANT:
- For skills, prioritize matching with existing skill tags before creating new ones.
- Normalize skill names to match common conventions (e.g., "Node JS" -> "Node.js", "Type Script" -> "TypeScript")
- Combine and deduplicate skills from all sources (explicit lists, work experience, projects)
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
                    $qualification = data_get($qual, 'data.qualification');
                    if (! in_array($qualification, ['Diploma', 'Bachelor', 'Master', 'PhD', 'Others'])) {
                        data_set($qual, 'data.qualification', 'Others');
                    }
                }
            }

            if (isset($result['social_media'])) {
                foreach ($result['social_media'] as &$social) {
                    $socialMedia = data_get($social, 'data.social_media');
                    if (! in_array($socialMedia, ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'])) {
                        data_set($social, 'data.social_media', 'others');
                    }
                }
            }

            if (isset($result['work_experience'])) {
                foreach ($result['work_experience'] as &$work) {
                    $employmentType = data_get($work, 'employment_type');
                    if (! in_array($employmentType, ['Full_time', 'Part_time', 'Contract', 'Internship', 'Freelance', 'Other'])) {
                        data_set($work, 'employment_type', 'Other');
                    }
                }
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Groq extraction failed: '.$e->getMessage());

            Notification::make('Groq extraction failed: '.$e->getMessage())
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [
                'personal_info' => [],
                'social_media' => [],
                'qualifications' => [],
                'work_experience' => [],
                'skills' => [],
            ];
        }
    }
}
