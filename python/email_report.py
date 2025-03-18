import smtplib
import sys
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders

SMTP_HOST = 'mail.supremecenter.com'
SMTP_USER = 'time@time.redlionsalvage.net'
SMTP_PASS = '7361dead'

def send_email(to_email):
    msg = MIMEMultipart()
    msg['From'] = SMTP_USER
    msg['To'] = to_email
    msg['Subject'] = 'TIMEMASTER Report'
    body = 'Attached is your TIMEMASTER report.'
    msg.attach(MIMEText(body, 'plain'))

    with open('report.csv', 'rb') as f:
        part = MIMEBase('application', 'octet-stream')
        part.set_payload(f.read())
        encoders.encode_base64(part)
        part.add_header('Content-Disposition', 'attachment; filename=report.csv')
        msg.attach(part)

    with smtplib.SMTP(SMTP_HOST) as server:
        server.login(SMTP_USER, SMTP_PASS)
        server.send_message(msg)

if __name__ == '__main__':
    send_email(sys.argv[1])