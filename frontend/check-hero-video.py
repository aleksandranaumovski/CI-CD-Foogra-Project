#!/usr/bin/env python3
"""Check the hero video is real footage rather than a placeholder card.

Usage:  python3 check-hero-video.py

Reads duration and dimensions straight out of the MP4 box structure (no ffprobe
needed) and derives the bitrate. The template shipped a 4K placeholder at
~470 kbps; real footage at that resolution runs 20,000-50,000 kbps, so bitrate
is a reliable tell.
"""

import struct
import sys
from pathlib import Path

VIDEO_DIR = Path(__file__).parent / "public" / "video"

# Rough floor for "this is real footage", by pixel count.
MIN_KBPS_BY_PIXELS = [
    (3840 * 2160, 8000),
    (2560 * 1440, 5000),
    (1920 * 1080, 2000),
    (1280 * 720, 1000),
    (0, 400),
]


def boxes(buf, start, end):
    """Yield (type, payload_start, payload_end) for each box in a range."""
    i = start
    while i + 8 <= end:
        size = struct.unpack(">I", buf[i:i + 4])[0]
        typ = buf[i + 4:i + 8].decode("latin-1", "replace")
        head = 8
        if size == 1:
            size = struct.unpack(">Q", buf[i + 8:i + 16])[0]
            head = 16
        elif size == 0:
            size = end - i
        if size < head:
            return
        yield typ, i + head, i + size
        i += size


def probe(path):
    buf = path.read_bytes()
    duration = width = height = None

    def walk(start, end):
        nonlocal duration, width, height
        for typ, ps, pe in boxes(buf, start, end):
            if typ == "mvhd":
                ver = buf[ps]
                off = ps + 4
                if ver == 1:
                    _, _, timescale, dur = struct.unpack(">QQIQ", buf[off:off + 28])
                else:
                    _, _, timescale, dur = struct.unpack(">IIII", buf[off:off + 16])
                if timescale:
                    duration = dur / timescale
            elif typ == "tkhd":
                w, h = struct.unpack(">II", buf[pe - 8:pe])
                w, h = w / 65536, h / 65536
                if w and h and (width is None or w > width):
                    width, height = w, h
            elif typ in ("moov", "trak", "mdia", "minf", "stbl", "edts"):
                walk(ps, pe)

    walk(0, len(buf))
    return len(buf), duration, width, height


def floor_for(pixels):
    for threshold, kbps in MIN_KBPS_BY_PIXELS:
        if pixels >= threshold:
            return kbps
    return 400


exit_code = 0
found = False

for name in ("intro.mp4", "intro.webm"):
    path = VIDEO_DIR / name
    if not path.exists():
        print(f"  {name:<12} MISSING")
        continue
    found = True

    if name.endswith(".mp4"):
        size, duration, w, h = probe(path)
        if not duration or not w:
            print(f"  {name:<12} could not parse — is it really an MP4?")
            exit_code = 1
            continue
        kbps = (size * 8 / duration) / 1000
        needed = floor_for(w * h)
        ok = kbps >= needed
        verdict = "real footage" if ok else f"PLACEHOLDER? (expected >{needed:,} kbps)"
        print(f"  {name:<12} {int(w)}x{int(h)}  {duration:5.1f}s  "
              f"{size / 1048576:6.2f}MB  {kbps:7.0f} kbps  {verdict}")
        if not ok:
            exit_code = 1
        if duration > 45:
            print(f"     note: {duration:.0f}s is long for a looping background; 8-20s is plenty")
        if size / 1048576 > 8:
            print(f"     note: {size / 1048576:.1f}MB will slow first paint; under ~5MB is better")
    else:
        size = path.stat().st_size
        print(f"  {name:<12} {size / 1048576:6.2f}MB  (WebM — secondary source, not parsed)")

if not found:
    print("  no video files at all in public/video/")
    exit_code = 1

sys.exit(exit_code)
