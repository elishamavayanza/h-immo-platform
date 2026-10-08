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
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState(false);
    const [isApplying, setIsApplying] = useState(false);
    const dragStart = useRef<{ pointerId: number; x: number; y: number; offsetX: number; offsetY: number } | null>(null);

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
        ctx.translate(size / 2 + offset.x, size / 2 + offset.y);
        ctx.rotate((rotation * Math.PI) / 180);
        const isSideways = Math.abs(rotation % 180) === 90;
        const rotatedWidth = isSideways ? image.height : image.width;
        const rotatedHeight = isSideways ? image.width : image.height;
        const scale = Math.max(size / rotatedWidth, size / rotatedHeight) * zoom;
        ctx.scale(scale, scale);
        ctx.drawImage(
            image,
            -image.width / 2,
            -image.height / 2,
            image.width,
            image.height
        );
        ctx.restore();
    }, [image, zoom, rotation, outputSize, offset]);

    const handleApply = () => {
        const canvas = canvasRef.current;
        if (!canvas || isApplying) return;
        setIsApplying(true);
        canvas.toBlob((blob) => {
            if (!blob) {
                setIsApplying(false);
                return;
            }
            const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            onApply(canvas.toDataURL('image/jpeg', 0.88), file);
            setIsApplying(false);
        }, 'image/jpeg', 0.88);
    };

    const handlePointerDown = (event: React.PointerEvent<HTMLCanvasElement>) => {
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        dragStart.current = { pointerId: event.pointerId, x: event.clientX, y: event.clientY, offsetX: offset.x, offsetY: offset.y };
        setIsDragging(true);
    };

    const handlePointerMove = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const start = dragStart.current;
        if (!start || start.pointerId !== event.pointerId) return;
        const bounds = event.currentTarget.getBoundingClientRect();
        setOffset({
            x: start.offsetX + (event.clientX - start.x) * (outputSize / bounds.width),
            y: start.offsetY + (event.clientY - start.y) * (outputSize / bounds.height),
        });
    };

    const handlePointerEnd = (event: React.PointerEvent<HTMLCanvasElement>) => {
        if (dragStart.current?.pointerId !== event.pointerId) return;
        dragStart.current = null;
        setIsDragging(false);
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
                        cursor: isDragging ? 'grabbing' : 'grab',
                        touchAction: 'none',
                    }}
                    onPointerDown={handlePointerDown}
                    onPointerMove={handlePointerMove}
                    onPointerUp={handlePointerEnd}
                    onPointerCancel={handlePointerEnd}
                    aria-label="Glissez la photo pour ajuster son cadrage"
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
                <button onClick={onCancel} disabled={isApplying}>{('Annuler')}</button>
                <button onClick={handleApply} disabled={isApplying}>{isApplying ? 'Préparation…' : 'Appliquer'}</button>
            </div>
        </div>
    );
}
