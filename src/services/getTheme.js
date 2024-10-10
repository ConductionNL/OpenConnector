export const getTheme = () => {
	if (document.body.hasAttribute('data-theme-light')) {
		return 'light'
	}
	if (document.body.hasAttribute('data-theme-default')) {
		return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'
	}
	return 'dark'
}
