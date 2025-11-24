# Spend Calculator

A premium-designed, simple spend calculator application built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features
- **User Authentication**: Secure login and registration.
- **Group Management**: Create groups with dynamic number of members (e.g., 3, 4, or more).
- **Expense Tracking**: Add expenses paid by specific members.
- **Split Calculation**: Automatically calculates who owes whom based on equal splitting.
- **Responsive Design**: Works on desktop and mobile with a modern glassmorphism aesthetic.

## Setup Instructions

1. **Requirements**:
   - XAMPP (or any PHP/MySQL environment).
   - PHP 7.4 or higher.
   - MySQL 5.7 or higher.

2. **Installation**:
   - Place the `project` folder in your server's root directory (e.g., `C:\xampp\htdocs\project`).
   - Ensure your MySQL server is running.
   - The application is configured to connect to MySQL with user `root` and no password (default XAMPP). If your settings differ, update `config.php`.

3. **Running the App**:
   - Open your browser and navigate to `http://localhost/project`.
   - The database and tables will be created automatically on the first run.

## Usage
1. **Register** a new account.
2. **Create a Group** from the dashboard (e.g., "Trip to Goa"). Add members (e.g., Alice, Bob, Charlie).
3. Click on the group to view details.
4. Use the **+** button to add expenses (e.g., Alice paid $50 for Dinner).
5. View the **Balances** sidebar to see who owes money.
