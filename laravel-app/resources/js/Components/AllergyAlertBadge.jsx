import React from 'react';
import {
    Box, Typography, Alert, Chip, Accordion,
    AccordionSummary, AccordionDetails,
} from '@mui/material';
import { ExpandMore as ExpandMoreIcon, WarningAmber as WarningAmberIcon, Block as BlockIcon, MedicalServices as MedicalServicesIcon } from '@mui/icons-material';

/**
 * AllergyAlertBadge
 *
 * Displays dangerous allergy alerts from the allergy gate result.
 * Shows per-substance severity with expandable reaction details.
 */
export default function AllergyAlertBadge({ allergyGate }) {
    if (!allergyGate || allergyGate.status === 'pass') {
        return (
            <Alert
                severity="success"
                icon={<MedicalServicesIcon />}
                sx={{ borderRadius: 2 }}
            >
                <strong>No dangerous surgical antibiotic allergies on record.</strong>
                {' '}Safe to proceed with standard perioperative antibiotic prophylaxis.
            </Alert>
        );
    }

    const allergies = allergyGate.allergies ?? [];
    const isHold    = allergyGate.status === 'hold';

    return (
        <Box>
            <Alert
                severity={isHold ? 'error' : 'warning'}
                icon={isHold ? <BlockIcon /> : <WarningAmberIcon />}
                sx={{ borderRadius: 2, mb: 1.5 }}
            >
                <strong>{isHold ? 'HOLD — ' : 'Warning — '}</strong>
                {allergyGate.message}
            </Alert>

            {allergies.map((allergy, idx) => (
                <Accordion
                    key={idx}
                    sx={{
                        background: isHold
                            ? 'rgba(244,67,54,0.07)'
                            : 'rgba(255,152,0,0.07)',
                        border: `1px solid ${isHold ? 'rgba(244,67,54,0.25)' : 'rgba(255,152,0,0.25)'}`,
                        borderRadius: '8px !important',
                        mb: 0.5,
                        '&:before': { display: 'none' },
                    }}
                >
                    <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, width: '100%' }}>
                            <Chip
                                label={allergy.criticality?.toUpperCase() ?? 'UNKNOWN'}
                                size="small"
                                color={allergy.criticality === 'high' ? 'error' : 'warning'}
                                sx={{ fontWeight: 700, fontSize: '0.65rem', minWidth: 60 }}
                            />
                            <Typography variant="subtitle2" sx={{ fontWeight: 600 }}>
                                {allergy.substance}
                            </Typography>
                            <Chip
                                label={`RxNorm: ${allergy.matched_code}`}
                                size="small"
                                variant="outlined"
                                sx={{ fontSize: '0.65rem', color: '#90CAF9', borderColor: 'rgba(144,202,249,0.3)', ml: 'auto' }}
                            />
                        </Box>
                    </AccordionSummary>
                    <AccordionDetails>
                        {allergy.reaction && (
                            <Typography variant="body2" sx={{ color: '#B0BEC5' }}>
                                <strong>Reaction:</strong> {allergy.reaction}
                            </Typography>
                        )}
                        <Typography variant="caption" sx={{ color: '#546E7A', display: 'block', mt: 0.5 }}>
                            Source: FHIR AllergyIntolerance resource — code lookup via RxNorm system
                        </Typography>
                    </AccordionDetails>
                </Accordion>
            ))}
        </Box>
    );
}
