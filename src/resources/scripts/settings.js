function enableConditionEvents(condition) {
	condition.querySelector('[data-condition-toggle]').onclick = ({ currentTarget }) => {
		const content = condition.querySelector('[data-condition-content]');

		content.style.display = content.style.display === 'none' ? 'block' : 'none';
		currentTarget.textContent = content.style.display === 'none' ? 'Expand' : 'Collapse';
	};
	condition.querySelector('[data-condition-delete]').onclick = () => {
		if (confirm('Are you sure you want to delete this condition?')) {
			condition.remove();
		}
	};
	condition.querySelector('[id$="-realm"]').addEventListener('input', function({ currentTarget }) {
		condition.querySelector('[data-condition-title]').textContent = currentTarget.value || 'New Condition';
	});
}

document.querySelector('[data-add-condition]').onclick = ({ currentTarget }) => {
	currentTarget.nextElementSibling.style.display = 'block';
	currentTarget.remove();
};

// Initialize existing conditions
document.querySelectorAll('[data-condition]')
	.forEach(c => { enableConditionEvents(c); });
