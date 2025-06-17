# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Laravel/PHP Commands
- `php artisan serve` - Start the Laravel development server
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Reset database and run seeders
- `php artisan queue:work` - Start queue worker for background jobs
- `php artisan tinker` - Interactive PHP/Laravel shell
- `php artisan test` - Run PHPUnit tests
- `composer install` - Install PHP dependencies
- `composer dump-autoload` - Regenerate autoloader files

### Frontend Commands
- `npm install` - Install Node.js dependencies
- `npm run dev` - Start Vite development server
- `npm run build` - Build assets for production

### Code Quality
- `vendor/bin/pint` - Run Laravel Pint code formatter (PSR-12 standard)

## Application Architecture

This is a Laravel 11 application for HR intern management built with:

### Core Technology Stack
- **Backend**: Laravel 11.45+ with PHP 8.1+
- **Admin Panel**: Filament 3.2+ (primary interface)
- **Frontend**: Vite + Laravel Mix
- **Database**: MySQL/PostgreSQL (migrations in `database/migrations/`)
- **Queue System**: Laravel Queues for background jobs
- **File Storage**: Spatie Media Library for file management

### Key Models and Relationships
- **Candidate**: Core entity representing job applicants
  - Has resume/document uploads via Media Library
  - Belongs to Position, has Tags, tracks CandidateStatus workflow
  - Soft deletes enabled, activity logging via Spatie
  
- **Position**: Job openings/internship positions
  - Has many Candidates and Emails
  - Uses PositionStatus enum, supports PositionType enum
  - Stores job posting URLs as array

- **Email**: Template system for candidate communications
  - Belongs to Position, supports CC recipients
  - Sortable, with document attachments

- **User**: Authentication for admin panel access

### Status Workflows
**CandidateStatus Flow**: PENDING → CONTACTED → TECHNICAL_TEST → INTERVIEW → OFFER_ACCEPTED → HIRED → COMPLETED (or WITHDRAWN at any stage)

**PositionStatus**: OPEN/CLOSED states for job postings

### Background Jobs (Queue System)
Located in `app/Jobs/`:
- `GenerateOfferLetterJob` - PDF offer letter generation
- `GenerateAttendanceReportJob` - Attendance tracking docs
- `GenerateCompletionCertJob` - Completion certificates
- `GenerateCompletionLetterJob` - Completion letters
- `GenerateWFHLetterJob` - Work from home documentation
- `SendEmailJob` - Email dispatch handling

### Services Architecture
- **GroqService** (`app/Services/GroqService.php`) - AI integration using Groq API for LLM functionality
- **PdfExtractorService** (`app/Services/PdfExtractorService.php`) - Resume parsing using Spatie PDF-to-text + AI analysis

### Filament Admin Structure
- **Resources**: `app/Filament/Resources/` - Main CRUD interfaces for Candidates, Positions, Emails, Tags, Users
- **Pages**: Custom pages for specialized views (audit, candidate-position relationships)
- **Widgets**: Dashboard components including CalendarWidget
- **Forms**: Complex form builders using Filament's form components with media uploads, rich editors, repeaters

### Key Features
- **Document Management**: Resume uploads, offer letter generation, completion certificates via Spatie Media Library
- **AI Integration**: Resume parsing and candidate information extraction using Groq LLM API
- **Activity Logging**: Full audit trail on all model changes using Spatie Activity Log
- **Tag System**: Skill tagging via Spatie Tags for candidate categorization
- **Queue-based PDF Generation**: Background job processing for document creation using DomPDF
- **Email Templates**: Reusable email templates with position-specific content

### File Organization
- **Models**: `app/Models/` - Eloquent models with relationships and media collections
- **Enums**: `app/Enums/` - Typed enums for status management and validation
- **Resources**: `app/Filament/Resources/` - Filament admin panel configuration
- **Templates**: `resources/views/template/` - Blade templates for PDF generation
- **Storage**: `storage/app/` - Generated documents and uploads

### Database Schema
Migration files show the evolution:
- Core tables: users, candidates, positions, emails
- Media library integration for file uploads
- Activity logging for audit trails
- Tag system for skills and categorization
- Queue jobs table for background processing

### Testing
- PHPUnit configuration in `phpunit.xml`
- Test files in `tests/Feature/` and `tests/Unit/`
- Uses SQLite in-memory database for testing environment