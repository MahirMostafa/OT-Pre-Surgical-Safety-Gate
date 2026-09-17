import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Box, Container, Grid, Typography, Button,
    Divider, Alert, Chip, Paper, CircularProgress,
    Snackbar, LinearProgress,
} from '@mui/material';
import {
    MonitorHeart as MonitorHeartIcon,
    CheckCircle as CheckCircleIcon,
    Block as BlockIcon,
    Warning as WarningIcon,
    Refresh as RefreshIcon,
} from '@mui/icons-material';
import { router } from '@inertiajs/react';

import PatientBanner   from '@/Components/PatientBanner';
import SafetyGateCard  from '@/Components/SafetyGateCard';
import LabResultTable  from '@/Components/LabResultTable';
import AllergyAlertBadge from '@/Components/AllergyAlertBadge';
import ExportButton    from '@/Components/ExportButton';

const OVERALL_CONFIG = {
    pass: {
        color:    '#4CAF50',
        bg:       'linear-gradient(135deg, rgba(76,175,80,0.15), rgba(56,142,60,0.08))',
        border:   'rgba(76,175,80,0.4)',
        icon:     CheckCircleIcon,
        label:    '✅ ALL CLEAR — Safe to Proceed',
        severity: 'success',
    },
    warn: {
        color:    '#FF9800',
        bg:       'linear-gradient(135deg, rgba(255,152,0,0.15), rgba(245,124,0,0.08))',
        border:   'rgba(255,152,0,0.4)',
        icon:     WarningIcon,
        label:    '⚠️ CAUTION — Review Warnings Before Proceeding',
        severity: 'warning',
    },
    hold: {
        color:    '#F44336',
        bg:       'linear-gradient(135deg, rgba(244,67,54,0.18), rgba(198,40,40,0.1))',
        border:   'rgba(244,67,54,0.5)',
        icon:     BlockIcon,
        label:    '🚫 HOLD — Do NOT Proceed Until Resolved',
        severity: 'error',
    },
};

export default function PreOpDashboard({ patient, gates, overall, checkedAt }) {
    const [confirming,   setConfirming]   = useState(false);
    const [confirmed,    setConfirmed]    = useState(false);
    const [confirmData,  setConfirmData]  = useState(null);
    const [snackOpen,    setSnackOpen]    = useState(false);
    const [snackMsg,     setSnackMsg]     = useState('');
    const [snackSeverity, setSnackSeverity] = useState('success');

    const overallCfg      = OVERALL_CONFIG[overall] ?? OVERALL_CONFIG.warn;
    const OverallIcon     = overallCfg.icon;
    const allergyGate     = gates?.find(g => g.id === 'allergy_check');

    const handleConfirm = async () => {
        setConfirming(true);
        try {
            const response = await fetch('/pre-op/confirm', {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find(r => r.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? ''
                    ),
                },
                body: JSON.stringify({}),
            });

            const data = await response.json();
            setConfirmData(data);
            setConfirmed(true);
            setSnackMsg(`Checklist confirmed. AuditEvent: ${data.fhir_audit_id ?? 'recorded'}`);
            setSnackSeverity('success');
            setSnackOpen(true);
        } catch (err) {
            setSnackMsg('Confirmation failed. Please try again.');
            setSnackSeverity('error');
            setSnackOpen(true);
        } finally {
            setConfirming(false);
        }
    };

    const handleRefresh = () => {
        router.reload({ only: ['patient', 'gates', 'overall', 'checkedAt'] });
    };

    return (
        <>
            <Head title="Pre-Op Safety Checklist" />

            {/* ─── App Header ─────────────────────────────────────────────── */}
            <Box
                sx={{
                    background: 'linear-gradient(90deg, rgba(0,188,212,0.08) 0%, transparent 60%)',
                    borderBottom: '1px solid rgba(0,188,212,0.15)',
                    px: 3,
                    py: 2,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 2,
                }}
            >
                <MonitorHeartIcon sx={{ color: '#00BCD4', fontSize: 32 }} />
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, lineHeight: 1, letterSpacing: '-0.02em' }}>
                        OT Pre-Surgical Safety Gate
                    </Typography>
                    <Typography variant="caption" sx={{ color: '#546E7A', letterSpacing: '0.08em' }}>
                        SMART on FHIR · HAPI FHIR R4 · LOINC · SNOMED CT · CPT · USCDI
                    </Typography>
                </Box>
                <Box sx={{ ml: 'auto', display: 'flex', gap: 1, alignItems: 'center' }}>
                    <Chip
                        label="FHIR R4"
                        size="small"
                        sx={{ backgroundColor: 'rgba(0,188,212,0.15)', color: '#4DD0E1', fontSize: '0.65rem', fontWeight: 700 }}
                    />
                    <Chip
                        label="HIPAA Safe"
                        size="small"
                        sx={{ backgroundColor: 'rgba(76,175,80,0.12)', color: '#81C784', fontSize: '0.65rem', fontWeight: 700 }}
                    />
                    <Button
                        id="refresh-checklist"
                        size="small"
                        startIcon={<RefreshIcon />}
                        onClick={handleRefresh}
                        sx={{ color: '#546E7A', ml: 1 }}
                    >
                        Refresh
                    </Button>
                </Box>
            </Box>

            <Container maxWidth="xl" sx={{ py: 3 }}>

                {/* ─── Overall Status Banner ───────────────────────────────── */}
                <Paper
                    sx={{
                        background: overallCfg.bg,
                        border: `1px solid ${overallCfg.border}`,
                        borderRadius: 2,
                        p: 2.5,
                        mb: 3,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 2,
                        boxShadow: `0 0 40px ${overallCfg.color}22`,
                    }}
                >
                    <OverallIcon sx={{ color: overallCfg.color, fontSize: 36 }} />
                    <Box sx={{ flex: 1 }}>
                        <Typography variant="h5" sx={{ fontWeight: 800, color: overallCfg.color, mb: 0.25 }}>
                            {overallCfg.label}
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#78909C' }}>
                            {gates?.length ?? 0} safety gates checked · {
                                gates?.filter(g => g.status === 'pass').length ?? 0
                            } PASS · {
                                gates?.filter(g => g.status === 'warn').length ?? 0
                            } WARN · {
                                gates?.filter(g => g.status === 'hold').length ?? 0
                            } HOLD
                        </Typography>
                    </Box>
                    {confirmed && (
                        <Chip
                            label={`✓ Confirmed — Audit: ${confirmData?.fhir_audit_id ?? '—'}`}
                            color="success"
                            variant="outlined"
                            sx={{ fontWeight: 600 }}
                        />
                    )}
                </Paper>

                {/* ─── Patient Banner ─────────────────────────────────────── */}
                <Box sx={{ mb: 3 }}>
                    <PatientBanner patient={patient} checkedAt={checkedAt} />
                </Box>

                {/* ─── Safety Gate Cards (2×2 grid) ───────────────────────── */}
                <Typography variant="overline" sx={{ color: '#00BCD4', mb: 1.5, display: 'block' }}>
                    Safety Gate Results
                </Typography>
                <Grid container spacing={2.5} sx={{ mb: 3 }}>
                    {(gates ?? []).map((gate) => (
                        <Grid item xs={12} sm={6} md={4} lg={2.4} key={gate.id}>
                            <SafetyGateCard gate={gate} />
                        </Grid>
                    ))}
                </Grid>

                <Divider sx={{ mb: 3 }} />

                {/* ─── Lab Results Table ───────────────────────────────────── */}
                <Box sx={{ mb: 3 }}>
                    <LabResultTable gates={gates} />
                </Box>

                <Divider sx={{ mb: 3 }} />

                {/* ─── Allergy Alerts ─────────────────────────────────────── */}
                <Box sx={{ mb: 3 }}>
                    <Typography variant="overline" sx={{ color: '#00BCD4', mb: 1.5, display: 'block' }}>
                        Surgical Antibiotic Allergy Status
                    </Typography>
                    <AllergyAlertBadge allergyGate={allergyGate} />
                </Box>

                <Divider sx={{ mb: 3 }} />

                {/* ─── Action Row ─────────────────────────────────────────── */}
                <Box
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 2,
                        p: 2.5,
                        background: 'rgba(13,21,39,0.6)',
                        border: '1px solid rgba(0,188,212,0.1)',
                        borderRadius: 2,
                    }}
                >
                    {/* Export */}
                    <ExportButton
                        overall={overall}
                        onExportSuccess={() => {
                            setSnackMsg('Pre-Op Summary exported. AuditEvent written to FHIR server.');
                            setSnackSeverity('info');
                            setSnackOpen(true);
                        }}
                    />

                    {/* Confirm & Proceed */}
                    <Box sx={{ textAlign: 'right' }}>
                        <Button
                            id="confirm-proceed-btn"
                            variant="contained"
                            size="large"
                            color={overall === 'hold' ? 'error' : 'primary'}
                            disabled={overall === 'hold' || confirming || confirmed}
                            startIcon={confirming
                                ? <CircularProgress size={18} color="inherit" />
                                : confirmed
                                ? <CheckCircleIcon />
                                : <MonitorHeartIcon />
                            }
                            onClick={handleConfirm}
                            sx={{
                                px: 4,
                                py: 1.5,
                                fontSize: '1rem',
                                boxShadow: overall === 'pass'
                                    ? '0 4px 20px rgba(76,175,80,0.35)'
                                    : undefined,
                            }}
                        >
                            {confirmed
                                ? 'Confirmed & Recorded'
                                : confirming
                                ? 'Recording...'
                                : overall === 'hold'
                                ? 'Cannot Proceed — HOLD Active'
                                : 'Confirm & Proceed to Surgery'}
                        </Button>
                        <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: '#37474F' }}>
                            Records FHIR AuditEvent + writes to audit_logs table
                        </Typography>
                    </Box>
                </Box>

            </Container>

            {/* ─── Snackbar ───────────────────────────────────────────────── */}
            <Snackbar
                open={snackOpen}
                autoHideDuration={5000}
                onClose={() => setSnackOpen(false)}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
            >
                <Alert
                    severity={snackSeverity}
                    onClose={() => setSnackOpen(false)}
                    sx={{ width: '100%', fontWeight: 500 }}
                >
                    {snackMsg}
                </Alert>
            </Snackbar>
        </>
    );
}
