import { Component, OnInit, AfterViewInit, ChangeDetectorRef, ViewChild, ElementRef, HostListener, OnDestroy } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Subject } from 'rxjs';
import { delay, takeUntil } from 'rxjs/operators';
import { ContextService } from 'sb-shared-lib';


@Component({
  selector: 'report',
  templateUrl: 'report.component.html',
  styleUrls: ['report.component.scss']
})
export class ReportComponent implements OnInit, AfterViewInit, OnDestroy {
    // @ViewChild('sbContainer') sbContainer: ElementRef;

    // rx subject for unsubscribing subscriptions on destroy
    private ngUnsubscribe = new Subject<void>();

    public ready: boolean = false;

    private default_descriptor: any = {
        // route: '/booking/object.id',
        context: {
            entity: 'contractika\\Report',
            view:   'form.default'
        }
    };


    private sa_id: number = 0;

    constructor(
        private route: ActivatedRoute,
        private context: ContextService
    ) {}

    public ngOnDestroy() {
        console.debug('ReportComponent::ngOnDestroy');
        this.ngUnsubscribe.next();
        this.ngUnsubscribe.complete();
    }

    public ngAfterViewInit() {
        console.debug('ReportComponent::ngAfterViewInit');

        this.context.setTarget('#sb-container-report');

        // if we just changed route, we want to ignore the previous context
        // const descriptor = this.context.getDescriptor();
        // if(!Object.keys(descriptor.context).length) {
            this.default_descriptor.context.domain = ["id", "=", this.sa_id];
            this.context.change(this.default_descriptor);
        //}
    }

    public ngOnInit() {
        console.debug('ReportComponent::ngOnInit');

        this.context.ready.pipe(takeUntil(this.ngUnsubscribe)).subscribe( (ready:boolean) => {
            this.ready = ready;
        });

        /*
            routing module is AppRoutingModule
        */
        this.route.params.pipe(takeUntil(this.ngUnsubscribe)).subscribe( async (params) => {
            this.sa_id = <number> parseInt(params['report_id'], 10);
            if(this.ready) {
                this.default_descriptor.context.domain = ["id", "=", this.sa_id];
                this.default_descriptor.context.reset = true;
                this.context.change(this.default_descriptor);
            }
        });
    }

}