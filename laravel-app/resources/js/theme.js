import { createTheme } from '@mui/material/styles';

/**
 * OT Safety Gate — Surgical Dark Theme
 *
 * Design System:
 * - Background: Deep navy (#0a0f1e) — clinical, focused environment
 * - Primary: Clinical teal (#00BCD4) — trustworthy, medical
 * - Success: Safe green (#4CAF50) — PASS status
 * - Warning: Alert amber (#FF9800) — WARN status
 * - Error: Danger red (#F44336) — HOLD status
 * - Font: Inter — clean, readable in clinical settings
 */
const theme = createTheme({
    palette: {
        mode: 'dark',
        background: {
            default: '#0a0f1e',
            paper: '#0d1527',
        },
        primary: {
            main: '#00BCD4',
            light: '#4DD0E1',
            dark: '#0097A7',
            contrastText: '#000000',
        },
        secondary: {
            main: '#7C4DFF',
            light: '#B388FF',
            dark: '#651FFF',
        },
        success: {
            main: '#4CAF50',
            light: '#81C784',
            dark: '#388E3C',
        },
        warning: {
            main: '#FF9800',
            light: '#FFB74D',
            dark: '#F57C00',
        },
        error: {
            main: '#F44336',
            light: '#EF9A9A',
            dark: '#C62828',
        },
        text: {
            primary: '#E8F4FD',
            secondary: '#90CAF9',
            disabled: '#546E7A',
        },
        divider: 'rgba(0, 188, 212, 0.15)',
    },

    typography: {
        fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
        h1: { fontWeight: 700, letterSpacing: '-0.02em' },
        h2: { fontWeight: 700, letterSpacing: '-0.01em' },
        h3: { fontWeight: 600 },
        h4: { fontWeight: 600 },
        h5: { fontWeight: 600 },
        h6: { fontWeight: 600 },
        subtitle1: { fontWeight: 500, letterSpacing: '0.01em' },
        body1: { lineHeight: 1.7 },
        body2: { lineHeight: 1.6 },
        button: {
            fontWeight: 600,
            letterSpacing: '0.05em',
            textTransform: 'none',
        },
        overline: {
            fontWeight: 700,
            letterSpacing: '0.12em',
            fontSize: '0.65rem',
        },
    },

    shape: {
        borderRadius: 12,
    },

    components: {
        MuiCssBaseline: {
            styleOverrides: {
                body: {
                    background: 'linear-gradient(135deg, #0a0f1e 0%, #0d1a2e 50%, #071018 100%)',
                    minHeight: '100vh',
                },
                // Google Font import
                '@import': "url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap')",
            },
        },
        MuiCard: {
            styleOverrides: {
                root: {
                    background: 'linear-gradient(145deg, rgba(13,21,39,0.95) 0%, rgba(10,15,30,0.98) 100%)',
                    border: '1px solid rgba(0,188,212,0.12)',
                    backdropFilter: 'blur(20px)',
                    boxShadow: '0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05)',
                    transition: 'all 0.3s ease',
                    '&:hover': {
                        border: '1px solid rgba(0,188,212,0.25)',
                        boxShadow: '0 12px 40px rgba(0,0,0,0.5), 0 0 20px rgba(0,188,212,0.08)',
                    },
                },
            },
        },
        MuiChip: {
            styleOverrides: {
                root: {
                    fontWeight: 600,
                    letterSpacing: '0.04em',
                },
            },
        },
        MuiButton: {
            styleOverrides: {
                root: {
                    borderRadius: 8,
                    padding: '10px 24px',
                    fontSize: '0.875rem',
                    fontWeight: 600,
                    transition: 'all 0.2s ease',
                },
                containedPrimary: {
                    background: 'linear-gradient(135deg, #00BCD4 0%, #0097A7 100%)',
                    boxShadow: '0 4px 15px rgba(0,188,212,0.3)',
                    '&:hover': {
                        background: 'linear-gradient(135deg, #4DD0E1 0%, #00BCD4 100%)',
                        boxShadow: '0 6px 20px rgba(0,188,212,0.45)',
                        transform: 'translateY(-1px)',
                    },
                },
            },
        },
        MuiLinearProgress: {
            styleOverrides: {
                root: {
                    borderRadius: 4,
                    height: 6,
                    backgroundColor: 'rgba(255,255,255,0.08)',
                },
            },
        },
        MuiTableCell: {
            styleOverrides: {
                root: {
                    borderBottom: '1px solid rgba(0,188,212,0.08)',
                },
                head: {
                    fontWeight: 700,
                    letterSpacing: '0.08em',
                    fontSize: '0.7rem',
                    textTransform: 'uppercase',
                    color: '#00BCD4',
                    backgroundColor: 'rgba(0,188,212,0.05)',
                },
            },
        },
        MuiAlert: {
            styleOverrides: {
                root: {
                    borderRadius: 10,
                    fontWeight: 500,
                },
            },
        },
        MuiDivider: {
            styleOverrides: {
                root: {
                    borderColor: 'rgba(0,188,212,0.12)',
                },
            },
        },
    },
});

export default theme;
