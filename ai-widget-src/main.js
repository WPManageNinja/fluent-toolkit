import './styles/app.scss'
import App from './App.svelte'

const targetId = 'fluent-ai-root';
let target = document.getElementById(targetId);

if (!target) {
    target = document.createElement('div');
    target.id = targetId;
    document.body.appendChild(target);
}

const app = new App({
    target: target,
})

export default app
