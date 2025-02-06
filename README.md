Employee ID Card Generator

Description

The Employee ID Card Generator is a WordPress plugin that allows administrators to create, manage, and generate employee ID cards with QR codes. The ID cards include employee details such as name, designation, employee code, blood group, address, and a photo. The generated ID cards can be downloaded as a PDF.

Features

Generate employee ID cards with a QR code.

Store employee details in a custom database table.

Upload and store employee photos.

Download ID cards in PDF format.

Auto-generate QR codes linked to employee details.

Secure nonce-based form submission to prevent CSRF attacks.

Installation

Download the plugin files and place them in the wp-content/plugins/employee-id-card-generator directory.

Activate the plugin through the Plugins menu in WordPress.

Go to the plugin settings and configure necessary options.

Use the ID Card Generator menu to create and manage employee ID cards.

Usage

Navigate to Employee ID Card Generator in the admin panel.

Fill in the employee details.

Click Generate ID Card to save and generate the ID.

View the ID card details and download the PDF.

Shortcode

You can display an employee's ID card using the following shortcode:

[id_card_display employee_code="EMP001"]

Replace EMP001 with the actual employee code.

Database Table Structure

The plugin creates a custom table wp_employee_id_cards with the following fields:

id (INT, Primary Key, Auto Increment)

name (VARCHAR)

designation (VARCHAR)

employee_code (VARCHAR, Unique)

blood_group (VARCHAR)

address1 (TEXT)

address2 (TEXT)

photo_url (VARCHAR)

qr_code_url (VARCHAR)

Dependencies

WordPress 5.5+

PHP 7.4+

MySQL 5.7+

TCPDF Library for PDF generation

Security Considerations

Nonce verification to prevent CSRF.

Sanitization of user inputs.

Secure file uploads with validation.

License

This plugin is licensed under the GNU General Public License v2.0.

Copyright (C) 2025 Your Name

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

Author

Your Name

Website: yourwebsite.com

Contact: [your email]

Contributing

Feel free to submit pull requests or report issues in the GitHub repository.

Changelog

Version 1.0.0

Initial release with ID card generation, QR codes, and PDF export.