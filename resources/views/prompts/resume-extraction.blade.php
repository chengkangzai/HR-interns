Extract information from Malaysian resumes and documents following these rules:

1. Personal Information:
- Name: Extract full name
- Phone Number:
  * Prioritize Malaysian phone numbers (formats: +60xx, 01x-xxxxxxx, 01x xxxxxxx)
  * If no Malaysian number found, extract other country numbers
  * Always format with international code (e.g., +60, +65, +44)
- Email: Extract email address

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
{!! $skills !!}
- If a skill doesn't match any existing tag, create a new one
- Normalize skill names (e.g., "Tailwind CSS" -> "TailwindCSS", "React.js" -> "React")
- Remove duplicates and combine skills from all sources

3. Social Media:
- Extract social media profiles
- Must be one of: 'linkedin', 'github', 'twitter', 'facebook', 'instagram', 'others'
- The url must be in the full address. For example, 'https://google.com' instead of 'google.com'

4. Qualifications:
- Only include formal academic qualifications from recognized institutions
- Qualification must be ONE of: 'Diploma', 'Bachelor', 'Master', 'PhD', 'Others'
- Ignore all online courses, certificates, and non-academic qualifications
- For qualifications not fitting the above categories exactly, use 'Others'
- Include only the highest qualification from each institution

5. Work Experience:
- Extract all work experiences
- Employment type must be ONE of: 'Full_time', 'Part_time', 'Contract', 'Internship', 'Freelance', 'Other'
- If employment type is not explicitly stated, default to 'Full_time'
- Location format must be 'City, State/Country' (e.g., 'Johor Bahru, Johor', 'Bukit Jalil, Kuala Lumpur')
- For current positions, set end_date as null and is_current as true

Maintain chronological order of qualifications and work experience (newest first).

IMPORTANT:
- For skills, prioritize matching with existing skill tags before creating new ones.
- Normalize skill names to match common conventions (e.g., "Node JS" -> "Node.js", "Type Script" -> "TypeScript")
- Combine and deduplicate skills from all sources (explicit lists, work experience, projects)

Resume text:
{!! $pdfText !!}
