import ftplib, sys, io
from io import BytesIO
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
ftp = ftplib.FTP('31.31.196.178', timeout=30)
ftp.login('u3413843', 'xA7iE9mY2odL7sY8')
d = 'www/admin.boostmarine.ru'

# Read config.php from server
buf = BytesIO()
ftp.retrbinary(f'RETR {d}/config.php', buf.write)
content = buf.getvalue().decode('utf-8', errors='replace')

# Find requireAuth function
idx = content.find('requireAuth')
if idx >= 0:
    print("requireAuth found at position", idx, flush=True)
    print(content[idx:idx+300], flush=True)
else:
    print("requireAuth NOT FOUND in config.php!", flush=True)

print("\n--- Session settings ---", flush=True)
for line in content.split('\n'):
    if 'session' in line.lower() or 'cookie' in line.lower():
        print(line.strip(), flush=True)

ftp.quit()
