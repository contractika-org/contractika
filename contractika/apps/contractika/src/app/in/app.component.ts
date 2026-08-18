import { Component, OnInit, NgZone, OnDestroy  } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ContextService } from 'sb-shared-lib';

@Component({
    selector: 'app',
    templateUrl: 'app.component.html',
    styleUrls: ['app.component.scss']
})
export class AppComponent implements OnInit, OnDestroy {
    // rx subject for unsubscribing subscriptions on destroy
    private ngUnsubscribe = new Subject<void>();

    public ready: boolean = false;
    public active: boolean = false;

    constructor(
        private context: ContextService,
        private zone: NgZone
    ) {}

    private getDescriptor() {
        return {
            context: {
                "entity": "core\\alert\\Message",
                "view": "list.dashboard"
            }
        };
    }

    public ngOnDestroy() {
        console.log('AppComponent::ngOnDestroy');
        this.ngUnsubscribe.next();
        this.ngUnsubscribe.complete();
    }

    public ngOnInit() {
        console.log('AppComponent::ngOnInit');
        this.context.ready.subscribe( (ready:boolean) => {
            console.log('AppComponent.context.ready', ready)
            this.ready = ready;
        });

        // if no context or all contexts have been closed, re-open default context (wait for route init)
        this.context.getObservable().pipe(takeUntil(this.ngUnsubscribe)).subscribe( () => {
            console.log('AppComponent.context - received context change');
            this.context.setTarget('#sb-container');
            // #memo - we don't trust the descriptor from the observable because it might have changed (if there was both a route and a context change)
            const descriptor: any = this.context.getDescriptor();
            if(descriptor.hasOwnProperty('context') && !Object.keys(descriptor.context).length) {
                console.log('AppComponent.context - empty context : reload default');
                this.ready = false;
                this.context.change(this.getDescriptor());
            }
        });

    }

    public ngAfterViewInit() {
        console.log('BookingsComponent::ngAfterViewInit');
        // this.context.change(this.getDescriptor);
        this.active = true;
    }


}