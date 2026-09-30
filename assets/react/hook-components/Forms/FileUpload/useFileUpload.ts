import { useState, useRef, useMemo } from 'react';

export type FileUploadVariant = 'default' | 'error' | 'success';
export type FileUploadSize = 'small' | 'medium' | 'large';

export interface UploadedFile {
    file: File;
    id: string;
}

export interface UseFileUploadProps {
    variant?: FileUploadVariant;
    fieldSize?: FileUploadSize;
    fullWidth?: boolean;
    disabled?: boolean;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    maxSizeInMB?: number;
    onFilesSelected?: (files: File[]) => void;
    className?: string;
}

export function useFileUpload({
                                  variant = 'default',
                                  fieldSize = 'medium',
                                  fullWidth = false,
                                  disabled = false,
                                  accept,
                                  multiple = false,
                                  maxFiles = 5,
                                  maxSizeInMB = 10,
                                  onFilesSelected,
                                  className = '',
                              }: UseFileUploadProps) {
    const [files, setFiles] = useState<UploadedFile[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const classes = useMemo(() => {
        const base = 'fileupload';
        const variantClass = `fileupload--${variant}`;
        const sizeClass = `fileupload--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'fileupload--full-width' : '';
        const disabledClass = disabled ? 'fileupload--disabled' : '';
        const dragClass = isDragging ? 'fileupload--dragging' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, dragClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, isDragging, className]);

    const addFiles = (fileList: FileList | null) => {
        if (!fileList) return;
        const newFiles = Array.from(fileList).slice(0, maxFiles - files.length);
        const validFiles = newFiles.filter((file) => file.size <= maxSizeInMB * 1024 * 1024);
        const mapped = validFiles.map((file) => ({ file, id: `${file.name}-${Date.now()}` }));
        setFiles((prev) => [...prev, ...mapped]);
        onFilesSelected?.(validFiles);
    };

    const removeFile = (id: string) => {
        setFiles((prev) => prev.filter((f) => f.id !== id));
    };

    const openFileDialog = () => {
        if (!disabled && inputRef.current) {
            inputRef.current.click();
        }
    };

    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragging(false);
        if (!disabled) {
            addFiles(e.dataTransfer.files);
        }
    };

    const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        if (!disabled) setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragging(false);
    };

    return {
        classes,
        files,
        inputRef,
        isDragging,
        addFiles,
        removeFile,
        openFileDialog,
        handleDrop,
        handleDragOver,
        handleDragLeave,
        accept,
        multiple,
        disabled,
    };
}
