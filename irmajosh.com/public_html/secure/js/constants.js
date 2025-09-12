export const API = {
  AUTH: '/secure/api/auth.php',
  REQUESTS: '/secure/api/requests.php',
  JOBS: '/secure/api/jobs.php',
  EVENTS: '/secure/api/events.php',
  FEATURES: '/secure/api/features.php'
};

export const STATUSES = {
  REQUEST: { PENDING: 'pending', SCHEDULED: 'scheduled', CANCELLED: 'cancelled' },
  JOB: { AVAILABLE: 'available', ACCEPTED: 'accepted', SCHEDULED: 'scheduled', CANCELLED: 'cancelled' }
};
