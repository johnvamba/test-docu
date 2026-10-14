export const APP_NAME = import.meta.env.VITE_APP_NAME || 'Ajaia Docs';

export const setPageTitle = (title: string) => {
  document.title = `${title} · ${APP_NAME}`;
};
