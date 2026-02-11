# e-LMS Moodle Project

This repository contains the configuration and setup for a local Moodle e-learning management system.

## Setup Instructions

1.  **Clone the repository** to your local machine.
2.  **Configuration Paths**:
    - The configuration files (`nginx.conf`, `php.ini`, `start_server.bat`) currently contain **absolute paths** (e.g., `c:\Users\jtrip\Desktop\Group 07\e-LMS`).
    - You **MUST** update these paths in all configuration files to match your local directory structure upon cloning.
3.  **Dependencies**:
    - Ensure PHP 8.x and Nginx are installed and their paths are correctly referenced in `start_server.bat`.
4.  **Run**:
    - Execute `start_server.bat` to start the Nginx and PHP-CGI services.
    - Access Moodle at `http://localhost:8081` (or the port configured in `nginx.conf`).
