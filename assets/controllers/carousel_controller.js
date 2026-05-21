import { Controller } from '@hotwired/stimulus';

// data-controller="carousel"
// data-carousel-interval-value="5000"
export default class extends Controller {
    static targets = ['slide', 'dot', 'caption'];
    static values = { interval: { type: Number, default: 5000 } };

    connect() {
        this.index = 0;
        this.show(this.index);
        this.start();
    }

    disconnect() {
        this.stop();
    }

    start() {
        this.stop();
        this.timer = setInterval(() => this.next(), this.intervalValue);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    next() {
        this.show((this.index + 1) % this.slideTargets.length);
    }

    prev() {
        this.show((this.index - 1 + this.slideTargets.length) % this.slideTargets.length);
    }

    go(event) {
        const to = parseInt(event.currentTarget.dataset.index, 10);
        if (!Number.isNaN(to)) {
            this.show(to);
            this.start();
        }
    }

    show(i) {
        this.slideTargets.forEach((el, idx) => {
            el.classList.toggle('is-active', idx === i);
        });
        if (this.hasDotTarget) {
            this.dotTargets.forEach((el, idx) => {
                el.classList.toggle('active', idx === i);
            });
        }
        if (this.hasCaptionTarget) {
            this.captionTargets.forEach((el, idx) => {
                el.classList.toggle('is-active', idx === i);
            });
        }
        this.index = i;
    }
}


