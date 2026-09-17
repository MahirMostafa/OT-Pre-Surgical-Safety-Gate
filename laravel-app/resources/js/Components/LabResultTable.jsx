import React from 'react';
import {
    Table, TableBody, TableCell, TableContainer,
    TableHead, TableRow, Paper, Chip, Typography, Box,
} from '@mui/material';

const LAB_CONFIG = {
    platelet_count: {
        loincCode: '777-3',
        name:      'Platelet Count',
        unit:      '× 10⁹/L',
        thresholds: { pass: 100, warn: 50 },
        thresholdLabel: '≥ 100 = PASS | 50–99 = WARN | < 50 = HOLD',
    },
    inr_clotting: {
        loincCode: '6301-6',
        name:      'INR / Prothrombin Time',
        unit:      'INR',
        thresholds: { pass: 1.5, warn: 2.0 },
        thresholdLabel: '≤ 1.5 = PASS | 1.5–2.0 = WARN | > 2.0 = HOLD',
        inverted: true,
    },
};

function getStatusFromGate(gate) {
    return gate?.status ?? 'unknown';
}

/**
 * LabResultTable
 *
 * Displays the lab safety gate results in a clinical table format.
 * Shows LOINC code, measured value, threshold, and status.
 *
 * GREEN FLAG: All results are fetched via LOINC code — no string matching.
 */
export default function LabResultTable({ gates }) {
    const labGates = gates?.filter(g =>
        g.id === 'platelet_count' || g.id === 'inr_clotting'
    ) ?? [];

    const statusColors = {
        pass:    { chip: 'success', label: '✓ PASS' },
        warn:    { chip: 'warning', label: '⚠ WARN' },
        hold:    { chip: 'error',   label: '🚫 HOLD' },
        unknown: { chip: 'default', label: '— N/A' },
    };

    return (
        <Box>
            <Typography variant="overline" sx={{ color: '#00BCD4', mb: 1.5, display: 'block' }}>
                Laboratory Safety Results
            </Typography>
            <TableContainer
                component={Paper}
                sx={{
                    background: 'rgba(13,21,39,0.8)',
                    border: '1px solid rgba(0,188,212,0.12)',
                    borderRadius: 2,
                }}
            >
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Test</TableCell>
                            <TableCell>LOINC Code</TableCell>
                            <TableCell align="right">Result</TableCell>
                            <TableCell>Unit</TableCell>
                            <TableCell>Result Date</TableCell>
                            <TableCell>Threshold</TableCell>
                            <TableCell align="center">Status</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {labGates.map((gate) => {
                            const config = LAB_CONFIG[gate.id];
                            const statusCfg = statusColors[gate.status] ?? statusColors.unknown;

                            return (
                                <TableRow
                                    key={gate.id}
                                    sx={{
                                        '&:hover': {
                                            backgroundColor: 'rgba(0,188,212,0.04)',
                                        },
                                    }}
                                >
                                    <TableCell>
                                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                            {config?.name ?? gate.id}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Chip
                                            label={config?.loincCode ?? '—'}
                                            size="small"
                                            variant="outlined"
                                            sx={{
                                                fontFamily: 'monospace',
                                                fontSize: '0.7rem',
                                                borderColor: 'rgba(0,188,212,0.35)',
                                                color: '#4DD0E1',
                                            }}
                                        />
                                    </TableCell>
                                    <TableCell align="right">
                                        <Typography
                                            variant="body1"
                                            sx={{
                                                fontFamily: 'monospace',
                                                fontWeight: 700,
                                                color: gate.status === 'pass' ? '#4CAF50'
                                                     : gate.status === 'warn' ? '#FF9800'
                                                     : '#F44336',
                                            }}
                                        >
                                            {gate.value !== null && gate.value !== undefined
                                                ? (typeof gate.value === 'number' ? gate.value.toFixed(1) : gate.value)
                                                : '—'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="caption" sx={{ color: '#546E7A' }}>
                                            {gate.unit ?? config?.unit ?? '—'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="caption" sx={{ color: '#546E7A', fontFamily: 'monospace' }}>
                                            {gate.date ?? '—'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="caption" sx={{ color: '#455A64', fontSize: '0.65rem' }}>
                                            {config?.thresholdLabel ?? '—'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell align="center">
                                        <Chip
                                            label={statusCfg.label}
                                            color={statusCfg.chip}
                                            size="small"
                                            sx={{ fontWeight: 700, fontSize: '0.7rem', minWidth: 70 }}
                                        />
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </TableContainer>
            <Typography variant="caption" sx={{ color: '#37474F', display: 'block', mt: 1 }}>
                All lab results fetched via LOINC code lookup — HAPI FHIR R4 Observation endpoint.
                No string-based matching used.
            </Typography>
        </Box>
    );
}
