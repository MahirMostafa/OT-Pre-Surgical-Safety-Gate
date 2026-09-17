import React from 'react';
import { Box, Chip, Typography, Divider } from '@mui/material';
import { Person as PersonOutlineIcon, LocalHospital as LocalHospitalIcon, AccessTime as AccessTimeIcon } from '@mui/icons-material';

/**
 * PatientBanner
 *
 * Displays PHI-safe patient context at the top of the dashboard.
 * Only shows FHIR ID, gender, age band — no names, DOBs, or phone numbers.
 */
export default function PatientBanner({ patient, checkedAt }) {
    return (
        <Box
            sx={{
                background: 'linear-gradient(135deg, rgba(0,188,212,0.12) 0%, rgba(0,151,167,0.06) 100%)',
                border: '1px solid rgba(0,188,212,0.25)',
                borderRadius: 2,
                p: 2.5,
                display: 'flex',
                alignItems: 'center',
                gap: 3,
                flexWrap: 'wrap',
            }}
        >
            {/* Patient Icon */}
            <Box
                sx={{
                    width: 52,
                    height: 52,
                    borderRadius: '50%',
                    background: 'linear-gradient(135deg, #00BCD4, #0097A7)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                    boxShadow: '0 4px 15px rgba(0,188,212,0.3)',
                }}
            >
                <PersonOutlineIcon sx={{ color: '#000', fontSize: 28 }} />
            </Box>

            {/* Patient Info */}
            <Box sx={{ flex: 1, minWidth: 200 }}>
                <Typography variant="overline" sx={{ color: '#00BCD4', display: 'block', mb: 0.5 }}>
                    Patient on Table
                </Typography>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 0.5 }}>
                    FHIR ID: <span style={{ color: '#4DD0E1' }}>{patient?.fhir_id ?? '—'}</span>
                </Typography>
                <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                    <Chip
                        label={`Gender: ${patient?.gender ?? 'unknown'}`}
                        size="small"
                        variant="outlined"
                        sx={{ borderColor: 'rgba(0,188,212,0.4)', color: '#90CAF9', fontSize: '0.7rem' }}
                    />
                    <Chip
                        label={`Age: ${patient?.age_band ?? 'unknown'}`}
                        size="small"
                        variant="outlined"
                        sx={{ borderColor: 'rgba(0,188,212,0.4)', color: '#90CAF9', fontSize: '0.7rem' }}
                    />
                    <Chip
                        label="PHI Protected"
                        size="small"
                        icon={<LocalHospitalIcon sx={{ fontSize: '0.8rem !important' }} />}
                        sx={{
                            backgroundColor: 'rgba(76,175,80,0.15)',
                            color: '#81C784',
                            borderColor: 'rgba(76,175,80,0.3)',
                            border: '1px solid',
                            fontSize: '0.7rem',
                        }}
                    />
                </Box>
            </Box>

            <Divider orientation="vertical" flexItem sx={{ display: { xs: 'none', md: 'block' } }} />

            {/* Timestamp */}
            <Box sx={{ textAlign: 'right' }}>
                <Typography variant="overline" sx={{ color: '#546E7A', display: 'block' }}>
                    Checklist Run
                </Typography>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, justifyContent: 'flex-end' }}>
                    <AccessTimeIcon sx={{ fontSize: 14, color: '#546E7A' }} />
                    <Typography variant="body2" sx={{ color: '#78909C', fontFamily: 'monospace' }}>
                        {checkedAt ? new Date(checkedAt).toLocaleTimeString() : '—'}
                    </Typography>
                </Box>
            </Box>
        </Box>
    );
}
