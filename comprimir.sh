#!/bin/bash

# Calidad del 0 al 100 (75-80 es el punto dulce)
CALIDAD=75

echo "Convirtiendo imágenes a WebP..."

# Convertir todos los .jpg, .jpeg y .png
for f in *.{jpg,jpeg,png}; do
    # Si no hay archivos de ese tipo, saltar
    [ -e "$f" ] || continue
    
    # Definir el nombre de salida (ej: foto.jpg -> foto.webp)
    output="${f%.*}.webp"
    
    echo "Procesando: $f -> $output"
    cwebp -q $CALIDAD "$f" -o "$output"
done

echo "¡Conversión finalizada!"
