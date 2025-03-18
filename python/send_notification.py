import smtplib
import sys
from email.mime.text import MIMEText

SMTP_HOST = 'mail.supremecenter.com'
SMTP_USER = 'time@time.redlionsalvage.net'
SMTP_PASS = '7361-Dead'

def send_email(message, to_email):
    msg = MIMEText(message)
    msg['From'] = SMTP_USER
    msg['To'] = to_email
    msg['Subject'] = 'TIMEMASTER Notification'
    
    with smtplib.SMTP(SMTP_HOST) as server:
        server.login(SMTP_USER, SMTP_PASS)
        server.send_message(msg)

if __name__ == '__main__':
    message = sys.argv[1]
    to_email = sys.argv[2]
    send_email(message, to_email)