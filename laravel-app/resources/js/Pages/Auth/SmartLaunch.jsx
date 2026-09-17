import React from 'react';
import { Head } from '@inertiajs/react';
import {
    Box, Container, Typography, Button, Paper,
    Divider, Chip, Alert,
} from '@mui/material';
import {
    MonitorHeart as MonitorHeartIcon,
    Lock as LockIcon,
    BugReport as BugReportIcon,
    Security as SecurityIcon,
    AccountTree as AccountTreeIcon,
} from '@mui/icons-material';

/**
 * SmartLaunch Page
 *
 * Entry point for the SMART on FHIR launch flow.
 * In production: redirects to FHIR authorize endpoint (PKCE).
 * In local dev: shows a bypass button for quick testing.
 */
export default function SmartLaunch({ isLocal, launchUrl, bypassUrl }) {
    return (
        <>
            <Head title="SMART Launch" />

            <Box
                sx={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: 'radial-gradient(ellipse at 50% 0%, rgba(0,188,212,0.08) 0%, transparent 70%)',
                    px: 2,
                }}
            >
                <Container maxWidth="sm">

                    {/* ─── Logo / Header ─────────────────────────────────── */}
                    <Box sx={{ textAlign: 'center', mb: 4 }}>
                        <Box
                            sx={{
                                width: 80,
                                height: 80,
                                borderRadius: '50%',
                                background: 'linear-gradient(135deg, #00BCD4, #0097A7)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                mx: 'auto',
                                mb: 2.5,
                                boxShadow: '0 8px 32px rgba(0,188,212,0.4)',
                            }}
                        >
                            <MonitorHeartIcon sx={{ color: '#000', fontSize: 44 }} />
                        </Box>
                        <Typography variant="h4" sx={{ fontWeight: 800, mb: 0.5, letterSpacing: '-0.02em' }}>
                            OT Pre-Surgical Safety Gate
                        </Typography>
                        <Typography variant="body1" sx={{ color: '#546E7A', maxWidth: 380, mx: 'auto' }}>
                            SMART on FHIR · HAPI FHIR R4 · HIPAA-compliant surgical safety verification
                        </Typography>
                    </Box>

                    {/* ─── Main Card ─────────────────────────────────────── */}
                    <Paper
                        sx={{
                            p: 4,
                            borderRadius: 3,
                            border: '1px solid rgba(0,188,212,0.2)',
                            background: 'linear-gradient(145deg, rgba(13,21,39,0.95), rgba(10,15,30,0.98))',
                        }}
                    >
                        {/* SMART Auth info */}
                        <Box sx={{ mb: 3 }}>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                                <SecurityIcon sx={{ color: '#00BCD4' }} />
                                <Typography variant="h6" sx={{ fontWeight: 700 }}>
                                    SMART on FHIR Authentication
                                </Typography>
                            </Box>
                            <Typography variant="body2" sx={{ color: '#78909C', mb: 2, lineHeight: 1.7 }}>
                                This application uses <strong style={{ color: '#4DD0E1' }}>OAuth 2.0 with PKCE</strong> (Proof Key for Code Exchange)
                                to securely authenticate against the EHR system. Your access token is stored
                                <strong style={{ color: '#4DD0E1' }}> server-side only</strong> — never in
                                browser storage.
                            </Typography>

                            <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mb: 2.5 }}>
                                {[
                                    { label: 'PKCE Enabled', color: '#4CAF50' },
                                    { label: 'Server-side tokens', color: '#4CAF50' },
                                    { label: 'Minimal scopes', color: '#00BCD4' },
                                    { label: 'AuditEvent trail', color: '#00BCD4' },
                                ].map(({ label, color }) => (
                                    <Chip
                                        key={label}
                                        label={label}
                                        size="small"
                                        sx={{
                                            backgroundColor: `${color}18`,
                                            color: color,
                                            border: `1px solid ${color}44`,
                                            fontWeight: 600,
                                            fontSize: '0.7rem',
                                        }}
                                    />
                                ))}
                            </Box>

                            <Alert severity="info" sx={{ borderRadius: 2, mb: 2.5 }}>
                                <Typography variant="body2">
                                    <strong>Scopes requested:</strong>{' '}
                                    <code style={{ fontSize: '0.75rem' }}>openid fhirUser launch patient/*.read</code>
                                    <br />
                                    Data minimization: only reads data required for the 4 safety checks.
                                </Typography>
                            </Alert>
                        </Box>

                        {/* SMART Launch Button */}
                        <Button
                            id="smart-launch-btn"
                            fullWidth
                            variant="contained"
                            size="large"
                            href={launchUrl}
                            startIcon={<LockIcon />}
                            sx={{
                                py: 1.75,
                                fontSize: '1rem',
                                mb: 2,
                                background: 'linear-gradient(135deg, #00BCD4 0%, #0097A7 100%)',
                                boxShadow: '0 4px 20px rgba(0,188,212,0.35)',
                                '&:hover': {
                                    background: 'linear-gradient(135deg, #4DD0E1 0%, #00BCD4 100%)',
                                    boxShadow: '0 6px 25px rgba(0,188,212,0.5)',
                                    transform: 'translateY(-1px)',
                                },
                            }}
                        >
                            Launch via SMART on FHIR
                        </Button>

                        {/* Dev bypass (local only) */}
                        {isLocal && (
                            <>
                                <Divider sx={{ mb: 2 }}>
                                    <Chip label="Local Development Only" size="small" sx={{ fontSize: '0.65rem', color: '#FF9800' }} />
                                </Divider>
                                <Button
                                    id="dev-bypass-btn"
                                    fullWidth
                                    variant="outlined"
                                    color="warning"
                                    href={bypassUrl}
                                    startIcon={<BugReportIcon />}
                                    sx={{ py: 1.25, mb: 1.5 }}
                                >
                                    Dev Bypass (Skip OAuth — Test Patient)
                                </Button>
                                <Typography variant="caption" sx={{ color: '#37474F', display: 'block', textAlign: 'center' }}>
                                    Bypass disabled in production. Uses test-patient-001 from HAPI FHIR sandbox.
                                </Typography>
                            </>
                        )}
                    </Paper>

                    {/* ─── Architecture info ─────────────────────────────── */}
                    <Box sx={{ mt: 3, textAlign: 'center' }}>
                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 1, mb: 1 }}>
                            <AccountTreeIcon sx={{ fontSize: 14, color: '#37474F' }} />
                            <Typography variant="caption" sx={{ color: '#37474F' }}>
                                Laravel 11 · SOLID Repository Pattern · Inertia.js + React · MUI v6 · Docker
                            </Typography>
                        </Box>
                        <Typography variant="caption" sx={{ color: '#263238' }}>
                            Built for OT Pre-Surgical Safety Gate — Health Interoperability Assessment
                        </Typography>
                    </Box>

                </Container>
            </Box>
        </>
    );
}
