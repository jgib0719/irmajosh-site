export const API = {
  AUTH: '/secure/api/auth.php', // Corrected path
  REQUESTS: '/secure/api/requests.php', // Corrected path
  JOBS: '/secure/api/jobs.php', // Corrected path
  EVENTS: '/secure/api/events.php' // Corrected path
};
export const STATUSES = {
  REQUEST: { PENDING:'pending', SCHEDULED:'scheduled', CANCELLED:'cancelled' },
  JOB: { AVAILABLE:'available', ACCEPTED:'accepted', SCHEDULED:'scheduled', CANCELLED:'cancelled' }
};
