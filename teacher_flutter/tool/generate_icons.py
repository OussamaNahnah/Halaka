"""Generate native launcher sizes from Halaka's existing logo; requires ImageMagick."""
import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = root / 'assets/brand/app-icon.png'
android = root / 'android/app/src/main/res'
for density, size, adaptive in [('mdpi', 48, 108), ('hdpi', 72, 162), ('xhdpi', 96, 216), ('xxhdpi', 144, 324), ('xxxhdpi', 192, 432)]:
    target = android / f'mipmap-{density}'
    subprocess.run(['magick', str(source), '-resize', f'{size}x{size}', str(target / 'ic_launcher.png')], check=True)
    inset = round(adaptive * .8)
    subprocess.run(['magick', str(source), '-resize', f'{inset}x{inset}', '-background', 'none', '-gravity', 'center', '-extent', f'{adaptive}x{adaptive}', str(target / 'ic_launcher_foreground.png')], check=True)
ios = root / 'ios/Runner/Assets.xcassets/AppIcon.appiconset'
for entry in json.loads((ios / 'Contents.json').read_text())['images']:
    size = round(float(entry['size'].split('x')[0]) * float(entry['scale'].rstrip('x')))
    subprocess.run(['magick', str(source), '-resize', f'{size}x{size}', '-alpha', 'off', str(ios / entry['filename'])], check=True)
