@echo off
if not exist "logs" mkdir "logs"
if not exist "logs\access.log" type nul > "logs\access.log"
if not exist "logs\error.log" type nul > "logs\error.log"
echo Starting PHP FastCGI...
start /b "PHP FastCGI" "C:\Program Files\php-8.5.1\php-cgi.exe" -b 127.0.0.1:9000
echo Starting Nginx...
start /b "Nginx" "C:\nginx-1.28.2\nginx.exe" -c "c:\Users\jtrip\Desktop\Group 07\e-LMS\nginx.conf"
echo Services started.
pause
