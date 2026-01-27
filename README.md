# HR Intern Management System

A comprehensive Laravel-based application for managing HR intern recruitment processes, built with Filament admin panel and AI-powered resume parsing capabilities.

## Features

- **Candidate Management**: Track candidates through the entire recruitment lifecycle
- **Position Management**: Create and manage internship positions with different types
- **AI-Powered Resume Parsing**: Automatic extraction of candidate information using Groq AI
- **Email Templates**: Customizable email templates for candidate communications
- **Document Generation**: Automated PDF generation for offer letters, completion certificates, and reports
- **Activity Logging**: Complete audit trail of all system changes
- **Tag System**: Skill-based tagging for candidate categorization
- **Queue-Based Processing**: Background job processing for document generation and email sending

## Technology Stack

- **Backend**: Laravel 11.45+ with PHP 8.1+
- **Admin Panel**: Filament 3.2+
- **Frontend**: Vite + Laravel Mix
- **Database**: MySQL/PostgreSQL
- **AI Integration**: Groq API for LLM functionality
- **PDF Processing**: Spatie PDF-to-text + DomPDF
- **File Management**: Spatie Media Library
- **Queue System**: Laravel Queues

## Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js and npm
- MySQL or PostgreSQL database
- Groq API key (for AI features)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd HR-interns
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure environment variables**
   
   Edit `.env` file with your settings:
   ```env
   APP_NAME="HR Intern Management"
   APP_URL=http://localhost:8000
   
   # Database Configuration
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hr_interns
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Mail Configuration (for email features)
   MAIL_MAILER=smtp
   MAIL_HOST=your_smtp_host
   MAIL_PORT=587
   MAIL_USERNAME=your_email
   MAIL_PASSWORD=your_password
   MAIL_FROM_ADDRESS="noreply@yourcompany.com"
   
   # Groq API (for AI resume parsing)
   GROQ_API_KEY=your_groq_api_key
   ```

5. **Database setup**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   ```

## Development

### Starting the application

```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in another terminal)
npm run dev

# Start queue worker (for background jobs)
php artisan queue:work
```

### Running tests

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test --filter TestClassName

# Run specific test file
php artisan test tests/Feature/ExampleTest.php
```

### Code formatting

```bash
# Format code using Laravel Pint
vendor/bin/pint
```

## Application Structure

### Core Models

- **Candidate**: Job applicants with resume uploads and status tracking
- **Position**: Internship positions with types and statuses
- **Email**: Template system for candidate communications
- **User**: Admin panel authentication

### Status Workflows

**Candidate Status Flow:**
PENDING → CONTACTED → TECHNICAL_TEST → INTERVIEW → OFFER_ACCEPTED → HIRED → COMPLETED

(Can be WITHDRAWN at any stage)

### Background Jobs

- `GenerateOfferLetterJob` - PDF offer letter generation
- `GenerateAttendanceReportJob` - Attendance tracking documents
- `GenerateCompletionCertJob` - Completion certificates
- `GenerateCompletionLetterJob` - Completion letters
- `GenerateWFHLetterJob` - Work from home documentation
- `SendEmailJob` - Email dispatch handling

### Key Services

- **PdfExtractorService**: Resume parsing using Spatie PDF-to-text + AI analysis via Prism PHP with OpenAI

## Filament Admin Panel

Access the admin panel at `/admin` after starting the development server.

### Default Admin User

After running seeders, use these credentials:
- Email: admin@example.com
- Password: password

### Main Resources

- **Candidates**: Manage candidate profiles, resumes, and status
- **Positions**: Create and manage internship positions
- **Emails**: Configure email templates
- **Tags**: Manage skill tags
- **Users**: Admin user management

## API Integration

### Groq AI Integration

The application uses Groq API for:
- Resume text extraction and parsing
- Candidate information extraction
- Automated data processing

Ensure you have a valid Groq API key in your `.env` file.

## File Storage

- **Resumes**: Stored via Spatie Media Library
- **Generated Documents**: Stored in `storage/app/`
- **Templates**: Blade templates in `resources/views/template/`

## Queue Configuration

For production environments, configure a proper queue driver:

```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

Then run queue workers:
```bash
php artisan queue:work --daemon
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and formatting
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For development guidance and architecture details, see [CLAUDE.md](CLAUDE.md).