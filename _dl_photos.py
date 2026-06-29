"""Download verified-working Unsplash photos to local assets.

Pages reference these local files so the site does not depend on remote image
availability at render time.
"""
import os
import urllib.request

OUT = "assets/img/photos"
os.makedirs(OUT, exist_ok=True)

# (local filename, source URL, context)
photos = [
    ("water-river.jpg", "https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=1400&q=70", "river landscape"),
    ("water-aerial.jpg", "https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1400&q=70", "water and agricultural landscape"),
    ("water-mountain.jpg", "https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1400&q=70", "mountain river"),
    ("forest-light.jpg", "https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1400&q=70", "forest light"),
    ("water-dusk.jpg", "https://images.unsplash.com/photo-1502082553048-f009c37129b9?auto=format&fit=crop&w=1400&q=70", "dusk landscape"),
    ("audience-talk.jpg", "https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1400&q=70", "conference audience"),
    ("books-library.jpg", "https://unsplash.com/photos/eXFG9dM_1f8/download?force=true&w=1400", "library shelves"),
    ("conference-room.jpg", "https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1400&q=70", "conference room"),
    ("meeting-office.jpg", "https://unsplash.com/photos/VBLHICVh-lI/download?force=true&w=1400", "office meeting"),
    ("underwater-blue.jpg", "https://unsplash.com/photos/XexawgzYOBc/download?force=true&w=1400", "underwater light"),
]

for fname, url, ctx in photos:
    out = os.path.join(OUT, fname)
    if os.path.exists(out):
        print("skip (exists):", fname)
        continue
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=30) as r, open(out, "wb") as f:
            f.write(r.read())
        size = os.path.getsize(out)
        print(f"OK {fname}: {size//1024}KB  ({ctx})")
    except Exception as e:
        print(f"FAIL {fname}: {e}")

print("\ndone")
