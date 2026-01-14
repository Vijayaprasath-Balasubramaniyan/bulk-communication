# Bulk SMS & Email Notification System

## Overview
This system is designed to send bulk SMS and Email notifications** reliably. It supports asynchronous processing, handles high-volume traffic, and provides a retry mechanism for failed messages. Failed jobs are logged and can be retried manually via the admin dashboard.

The system uses:
- SendGrid for Email notifications
- Twilio for SMS notifications
- Bootstrap 5, Chart.js, and jQuery for the admin


## System Flow

1. **Campaign Planning**
   - Weekly greetings or notifications are planned as a weekly campaign.
   - Users are selected to be added to the message queue via a weekly cron job.

2. **Queue Population**
   - Cron job `enqueue_users.php` runs weekly to insert users into the message queue.
   - Each job inserts a batch of users as per the configured limit (`LIMIT_DATA`).

3. **Message Sending**
   - Separate cron jobs handle SMS and Email sending every 5 minutes:
     - `sms_handler.php` → sends SMS via Twilio
     - `email_handler.php` → sends Emails via SendGrid
   - Each job processes a batch of messages and updates their status:
     - `PENDING` → `SENT` or `FAILED`
   - Failed messages are logged in the `message_logs` table.

4. **Retry Mechanism**
   - Failed messages are retried automatically based on `MAX_RETRIES` and exponential backoff.
   - Admin can manually retry messages from the dashboard if maximum retries are reached.

5. **Admin**
   - Displays queue status, retry counts, and message logs.
   - Allows filtering by channel (SMS/EMAIL) and status (PENDING/SENT/FAILED).
   - Provides manual retry for failed messages excceding max retry.



## Features

- Batch Processing: Sends messages in configurable batches (`LIMIT_DATA`).
- Asynchronous Handling: Separate cron jobs for queue population and sending.
- Retry & Logging: Failed messages are logged with error reasons; retries are automated.
- Manual Retry: Admin can trigger manual retries from the dashboard.
- Dashboard: Stats and message queue.
- Environment Config: API keys, database credentials, and other secrets stored in `.env`.



## Environment Variables (`.env`)

env

DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=

MAX_RETRIES=3

SENDGRID_API_KEY=SG.XXXXXXXXXXXXXXXXXXXXXXXXXXXXX
SENDGRID_TEMPLATE_ID=d-XXXXXXXXXXXXXXXXXXXXXXXXX
SENDGRID_FROM_EMAIL=XXXXXXX@gmail.com
SENDGRID_API=https://api.sendgrid.com/v3/mail/send

TWILIO_SID=XXXXXXXXXXXXXXXXXXXXXXXXX
TWILIO_TOKEN=XXXXXXXXXXXXXXXXXXXXXXXXXXXXX
TWILIO_FROM=+1XXXXXXXXXX

LIMIT_DATA=100
APP_URL=http://localhost/bulk-communication


## Cron Job Setup

Edit your crontab (crontab -e) and add:

# Weekly queue population (every Sunday at midnight)
0 0 * * 0 php /var/www/bulk-communication/cron/enqueue_users.php

# SMS sending every 5 minutes
*/5 * * * * php /var/www/bulk-communication/handler/sms_handler.php

# Email sending every 5 minutes
*/5 * * * * php /var/www/bulk-communication/handler/email_handler.php
