import React, { useEffect, useRef, useState } from 'react';

interface ImageEditorProps {
    src: string;
    onCancel: () => void;
    onApply: (result: string, file?: File) => void;
    aspect?: number;       // ratio, 1 = carré
    outputSize?: number;   // taille de sortie en pixels
    shape?: 'circle' | 'square'; // forme de la zone de cadrage
}

export function ImageEditor({ src, onCancel, onApply, aspect = 1, outputSize = 300, shape = 'circle' }: ImageEditorProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [image, setImage] = useState<HTMLImageElement | null>(null);

    useEffect(() => {
        const img = new Image();
        img.src = src;
        img.onload = () => setImage(img);
    }, [src]);

    useEffect(() => {
        if (!image || !canvasRef.current) return;
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const size = outputSize;
        canvas.width = size;
        canvas.height = size;

        ctx.clearRect(0, 0, size, size);
        ctx.save();
        ctx.translate(size / 2, size / 2);
        ctx.rotate((rotation * Math.PI) / 180);
        const scale = zoom;
        ctx.scale(scale, scale);
        ctx.drawImage(
            image,
            -image.width / 2,
            -image.height / 2,
            image.width,
            image.height
        );
        ctx.restore();
    }, [image, zoom, rotation, outputSize]);

    const handleApply = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        // Convertir dataURL en File
        fetch(dataUrl)
            .then((res) => res.blob())
            .then((blob) => {
                const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                onApply(dataUrl, file);
            });
    };

    return (
        <div className="image-editor">
            <div className="image-editor__canvas-container">
                <canvas
                    ref={canvasRef}
                    style={{
                        width: outputSize,
                        height: outputSize,
                        borderRadius: shape === 'square' ? 0 : '50%',
                    }}
                />
            </div>

            <div className="image-editor__controls">
                <div className="image-editor__control">
                    <label>{('Zoom')}</label>
                    <input
                        type="range"
                        min={0.5}
                        max={3}
                        step={0.1}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                    />
                </div>
                <div className="image-editor__control">
                    <label>{('Rotation')}</label>
                    <button onClick={() => setRotation((prev) => prev - 90)}>-90°</button>
                    <button onClick={() => setRotation((prev) => prev + 90)}>+90°</button>
                </div>
            </div>

            <div className="image-editor__actions">
                <button onClick={onCancel}>{('Annuler')}</button>
                <button onClick={handleApply}>{('Appliquer')}</button>
            </div>
        </div>
    );
}
