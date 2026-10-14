import axios, { type AxiosError } from 'axios';
import type { ValidationErrors } from '@/types/api';

export const http = axios.create({
  withCredentials: true,
  withXSRFToken: true, // sends the XSRF-TOKEN cookie back as the X-XSRF-TOKEN header
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

/** Must run before login/register so Laravel sets the XSRF-TOKEN cookie. */
export const csrf = () => http.get('/sanctum/csrf-cookie');

export function isStatus(error: unknown, status: number): error is AxiosError {
  return axios.isAxiosError(error) && error.response?.status === status;
}

/** Field errors from a 422, or an empty object. */
export function fieldErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError<ValidationErrors>(error) && error.response?.status === 422) {
    return error.response.data.errors ?? {};
  }
  return {};
}

/** A sentence that is safe to show the user for any failed request. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (!axios.isAxiosError<{ message?: string }>(error)) return fallback;
  if (!error.response) return 'You appear to be offline. Check your connection and try again.';
  const { status, data } = error.response;
  if (status === 403) return 'You don’t have permission to do that.';
  if (status === 404) return 'That document no longer exists, or it was unshared with you.';
  if (status === 413) return 'That file is too large.';
  if (status === 419) return 'Your session expired. Refresh the page and sign in again.';
  if (status === 429) return 'Too many requests. Wait a moment and try again.';
  if (status === 422 && data?.message) return data.message;
  return fallback;
}
