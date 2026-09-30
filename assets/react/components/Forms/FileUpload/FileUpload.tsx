import React from 'react';
import { useFileUpload, UseFileUploadProps } from '../../../hook-components/Forms/FileUpload';

const UploadIcon = () => (
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="17 8 12 3 7 8" />
        <line x1="12" y1="3" x2="12" y2="15" />
    </svg>
);

const FileIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
    </svg>
);

const RemoveIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
);

export interface FileUploadProps extends UseFileUploadProps {
    label?: string;
    hint?: string;
}

export function FileUpload({
                               variant = 'default',
                               fieldSize = 'medium',
                               fullWidth = true,
                               disabled = false,
                               accept,
                               multiple = false,
                               maxFiles = 5,
                               maxSizeInMB = 10,
                               onFilesSelected,
                               className,
                               label,
                               hint,
                           }: FileUploadProps) {
    const effectiveLabel = label ?? t('Cliquez ou déposez des fichiers ici');
    const {
        classes,
        files,
        inputRef,
        addFiles,
        removeFile,
        openFileDialog,
        handleDrop,
        handleDragOver,
        handleDragLeave,
    } = useFileUpload({
        variant,
        fieldSize,
        fullWidth,
        disabled,
        accept,
        multiple,
        maxFiles,
        maxSizeInMB,
        onFilesSelected,
        className,
    });

    return (
        <div className={classes}>
            <div
                className="fileupload__dropzone"
                onClick={openFileDialog}
                onDrop={handleDrop}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                role="button"
                tabIndex={disabled ? -1 : 0}
                onKeyDown={(e) => {
                    if ((e.key === 'Enter' || e.key === ' ') && !disabled) {
                        e.preventDefault();
                        openFileDialog();
                    }
                }}
            >
                <UploadIcon />
                <span className="fileupload__label">{effectiveLabel}</span>
                {hint && <span className="fileupload__hint">{hint}</span>}
                <input
                    ref={inputRef}
                    type="file"
                    className="fileupload__input"
                    accept={accept}
                    multiple={multiple}
                    disabled={disabled}
                    onChange={(e) => addFiles(e.target.files)}
                />
            </div>

            {files.length > 0 && (
                <ul className="fileupload__list">
                    {files.map((file) => (
                        <li key={file.id} className="fileupload__item">
                            <FileIcon />
                            <span className="fileupload__filename">{file.file.name}</span>
                            <span className="fileupload__filesize">({(file.file.size / 1024).toFixed(1)} Ko)</span>
                            <button
                                type="button"
                                className="fileupload__remove"
                                onClick={() => removeFile(file.id)}
                                aria-label={('Supprimer {{ nom }}', { nom: file.file.name })}
                            >
                                <RemoveIcon />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
