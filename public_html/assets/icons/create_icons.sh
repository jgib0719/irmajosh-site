#!/bin/bash
# Create simple placeholder icons using ImageMagick
for size in 72 96 128 144 152 192 384 512; do
    convert -size ${size}x${size} xc:"#667eea" \
            -gravity center \
            -pointsize $((size/3)) \
            -fill white \
            -font DejaVu-Sans-Bold \
            -annotate +0+0 "IJ" \
            icon-${size}x${size}.png
done
echo "Icons created"
