import React, { useState } from 'react';
import { Button, Box, CircularProgress, Tooltip, Typography } from '@mui/material';
import { Download as DownloadIcon, Verified as VerifiedIcon } from '@mui/icons-material';
import { router } from '@inertiajs/react';

/**
 * ExportButton
 *
 * Triggers USCDI-compliant ClinicalImpression export.
 * Also writes a FHIR AuditEvent for the export action.
 * Disabled when overall status is 'hold' (surgeon must not proceed).
 */
export default function ExportButton({ overall, onExportSuccess }) {
    const [loading, setLoading]   = useState(false);
    const [exported, setExported] = useState(false);

    const isDisabled = overall === 'hold' || loading;

    const handleExport = async () => {
        setLoading(true);
        try {
            const response = await fetch('/pre-op/export', {
                method: 'GET',
                headers: {
                    'Accept': 'application/fhir+json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': document.cookie
                        .split('; ')
                        .find(row => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                },
            });

            if (!response.ok) throw new Error('Export failed');

            const blob     = await response.blob();
            const url      = window.URL.createObjectURL(blob);
            const filename = `pre-op-summary-${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.json`;

            // Trigger download
            const link  = document.createElement('a');
            link.href   = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            setExported(true);
            onExportSuccess?.();
        } catch (err) {
            console.error('Export failed:', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 1 }}>
            <Tooltip
                title={overall === 'hold'
                    ? 'Cannot export — one or more safety gates are in HOLD status. Resolve issues before proceeding.'
                    : 'Download USCDI-compliant ClinicalImpression JSON. A FHIR AuditEvent will be recorded.'}
                arrow
            >
                <span>
                    <Button
                        id="export-pre-op-summary"
                        variant="outlined"
                        size="large"
                        startIcon={exported
                            ? <VerifiedIcon />
                            : loading
                            ? <CircularProgress size={18} color="inherit" />
                            : <DownloadIcon />
                        }
                        onClick={handleExport}
                        disabled={isDisabled}
                        sx={{
                            borderColor: exported ? 'success.main' : 'primary.main',
                            color:       exported ? 'success.main' : 'primary.main',
                            '&:hover': {
                                borderColor: exported ? 'success.light' : 'primary.light',
                                backgroundColor: exported
                                    ? 'rgba(76,175,80,0.08)'
                                    : 'rgba(0,188,212,0.08)',
                            },
                        }}
                    >
                        {exported
                            ? 'Summary Exported'
                            : loading
                            ? 'Exporting...'
                            : 'Export Pre-Op Summary'}
                    </Button>
                </span>
            </Tooltip>
            <Typography variant="caption" sx={{ color: '#37474F' }}>
                USCDI-compliant FHIR ClinicalImpression · PHI-stripped · AuditEvent recorded
            </Typography>
        </Box>
    );
}
