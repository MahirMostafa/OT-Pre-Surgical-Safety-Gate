import React from 'react';
import { Box, Card, CardContent, Typography, Chip, LinearProgress } from '@mui/material';
import {
    CheckCircleOutlined as CheckCircleOutlineIcon,
    WarningAmber as WarningAmberIcon,
    Block as BlockIcon,
    HourglassEmpty as HourglassEmptyIcon,
} from '@mui/icons-material';

const STATUS_CONFIG = {
    pass: {
        color: 'success.main',
        bgGradient: 'linear-gradient(135deg, rgba(76,175,80,0.12) 0%, rgba(56,142,60,0.06) 100%)',
        borderColor: 'rgba(76,175,80,0.3)',
        glowColor: 'rgba(76,175,80,0.15)',
        icon: CheckCircleOutlineIcon,
        chipLabel: 'PASS',
        chipColor: 'success',
        pulse: '#4CAF50',
    },
    warn: {
        color: 'warning.main',
        bgGradient: 'linear-gradient(135deg, rgba(255,152,0,0.12) 0%, rgba(245,124,0,0.06) 100%)',
        borderColor: 'rgba(255,152,0,0.3)',
        glowColor: 'rgba(255,152,0,0.12)',
        icon: WarningAmberIcon,
        chipLabel: 'WARN',
        chipColor: 'warning',
        pulse: '#FF9800',
    },
    hold: {
        color: 'error.main',
        bgGradient: 'linear-gradient(135deg, rgba(244,67,54,0.15) 0%, rgba(198,40,40,0.08) 100%)',
        borderColor: 'rgba(244,67,54,0.4)',
        glowColor: 'rgba(244,67,54,0.15)',
        icon: BlockIcon,
        chipLabel: '🚫 HOLD',
        chipColor: 'error',
        pulse: '#F44336',
    },
};

/**
 * SafetyGateCard
 *
 * Reusable card for each of the 4 pre-op safety gates.
 * Shows animated status dot, gate label, result message, and optional value.
 */
export default function SafetyGateCard({ gate, loading = false }) {
    const status = gate?.status ?? 'loading';
    const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.warn;
    const Icon   = config.icon ?? HourglassEmptyIcon;

    const gateLabels = {
        procedure_diagnosis: 'Procedure vs. Diagnosis',
        platelet_count:      'Platelet Count',
        inr_clotting:        'INR / Clotting Time',
        allergy_check:       'Surgical Allergy Check',
    };

    const gateDescriptions = {
        procedure_diagnosis: 'CPT code ↔ SNOMED CT cross-validation',
        platelet_count:      'LOINC 777-3 — threshold ≥ 100 × 10⁹/L',
        inr_clotting:        'LOINC 6301-6 — threshold ≤ 1.5 INR',
        allergy_check:       'FHIR AllergyIntolerance — surgical antibiotics',
    };

    return (
        <Card
            sx={{
                height: '100%',
                background: config.bgGradient,
                border: `1px solid ${config.borderColor}`,
                position: 'relative',
                overflow: 'visible',
                transition: 'all 0.4s ease',
                '&:hover': {
                    transform: 'translateY(-3px)',
                    boxShadow: `0 16px 40px rgba(0,0,0,0.5), 0 0 30px ${config.glowColor}`,
                },
            }}
        >
            {/* Animated status pulse dot */}
            {!loading && (
                <Box
                    sx={{
                        position: 'absolute',
                        top: 14,
                        right: 14,
                        width: 10,
                        height: 10,
                        borderRadius: '50%',
                        backgroundColor: config.pulse,
                        boxShadow: `0 0 0 0 ${config.pulse}`,
                        animation: status === 'hold'
                            ? 'holdPulse 1.5s infinite'
                            : 'none',
                        '@keyframes holdPulse': {
                            '0%':   { boxShadow: `0 0 0 0 ${config.pulse}66` },
                            '70%':  { boxShadow: `0 0 0 8px ${config.pulse}00` },
                            '100%': { boxShadow: `0 0 0 0 ${config.pulse}00` },
                        },
                    }}
                />
            )}

            <CardContent sx={{ p: 2.5 }}>
                {/* Loading state */}
                {loading ? (
                    <Box>
                        <Typography variant="overline" sx={{ color: '#546E7A' }}>
                            Running check...
                        </Typography>
                        <LinearProgress sx={{ mt: 2 }} />
                    </Box>
                ) : (
                    <>
                        {/* Header row */}
                        <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5, mb: 2 }}>
                            <Box
                                sx={{
                                    width: 40,
                                    height: 40,
                                    borderRadius: 2,
                                    background: `${config.pulse}22`,
                                    border: `1px solid ${config.pulse}44`,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    flexShrink: 0,
                                }}
                            >
                                <Icon sx={{ color: config.pulse, fontSize: 22 }} />
                            </Box>
                            <Box sx={{ flex: 1 }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 700, lineHeight: 1.2, mb: 0.3 }}>
                                    {gateLabels[gate.id] ?? gate.id}
                                </Typography>
                                <Typography variant="caption" sx={{ color: '#546E7A', fontFamily: 'monospace', fontSize: '0.65rem' }}>
                                    {gateDescriptions[gate.id] ?? ''}
                                </Typography>
                            </Box>
                        </Box>

                        {/* Status chip */}
                        <Chip
                            label={config.chipLabel}
                            color={config.chipColor}
                            size="small"
                            sx={{ mb: 1.5, fontWeight: 700, fontSize: '0.75rem', letterSpacing: '0.06em' }}
                        />

                        {/* Result message */}
                        <Typography variant="body2" sx={{ color: '#B0BEC5', lineHeight: 1.6 }}>
                            {gate.message}
                        </Typography>

                        {/* Value display */}
                        {gate.value !== null && gate.value !== undefined && (
                            <Box
                                sx={{
                                    mt: 1.5,
                                    px: 1.5,
                                    py: 0.75,
                                    borderRadius: 1.5,
                                    backgroundColor: 'rgba(0,0,0,0.25)',
                                    border: '1px solid rgba(255,255,255,0.06)',
                                    display: 'inline-flex',
                                    alignItems: 'baseline',
                                    gap: 0.5,
                                }}
                            >
                                <Typography variant="h6" sx={{ fontFamily: 'monospace', color: config.pulse, fontWeight: 700 }}>
                                    {typeof gate.value === 'number' ? gate.value.toFixed(1) : gate.value}
                                </Typography>
                                {gate.unit && (
                                    <Typography variant="caption" sx={{ color: '#546E7A' }}>
                                        {gate.unit}
                                    </Typography>
                                )}
                            </Box>
                        )}

                        {/* Lab date badge */}
                        {gate.date && (
                            <Typography variant="caption" sx={{ display: 'block', mt: 1, color: '#546E7A' }}>
                                Result date: {gate.date}
                            </Typography>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
