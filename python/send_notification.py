import smtplib
import sys
from email.mime.text import MIMEText

SMTP_HOST = 'mail.supremecenter.com'
SMTP_USER = 'time@time.redlionsalvage.net'
SMTP_PASS = '7361-Dead'

def send_email(message, to_email):
    # Create more informative subject line based on message content
    # Extract username and action for the subject
    parts = message.split(' just ')
    if len(parts) >= 2:
        username = parts[0]
        action_part = parts[1].split(' at ')[0]
        subject = f"TIMEMASTER Alert: {username} just {action_part}"
    else:
        subject = "TIMEMASTER Notification"
    
    msg = MIMEText(message)
    msg['From'] = SMTP_USER
    msg['To'] = to_email
    msg['Subject'] = subject
    
    with smtplib.SMTP(SMTP_HOST) as server:
        server.login(SMTP_USER, SMTP_PASS)
        server.send_message(msg)

if __name__ == '__main__':
    message = sys.argv[1]
    to_email = sys.argv[2]
    send_email(message, to_email)