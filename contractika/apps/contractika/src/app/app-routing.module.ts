import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';
import { AppComponent } from './in/app.component';
import { SALineComponent } from './in/serviceaccountline/serviceaccountline.component';
import { ServiceAccountComponent } from './in/serviceaccount/serviceaccount.component';
import { ReportComponent } from './in/report/report.component';
import { CustomerComponent } from './in/customer/customer.component';

const routes: Routes = [
    /* routes specific to current app */
    {
        path: 'serviceaccountline/:sa_line_id',
        component: SALineComponent
    },
    {
        path: 'serviceaccount/:service_account_id',
        component: ServiceAccountComponent
    },
    {
        path: 'customer/:customer_id',
        component: CustomerComponent
    },
    {
        path: 'report/:report_id',
        component: ReportComponent
    },
    {
    /*
        default route, for bootstrapping the App
        1) display a loader and try to authenticate
        2) store user details (roles and permissions)
        3) redirect to applicable page (/apps or /auth)
        */
    path: '',
    component: AppComponent
    }
];

@NgModule({
  imports: [
    RouterModule.forRoot(routes, { preloadingStrategy: PreloadAllModules, onSameUrlNavigation: 'reload', useHash: true })
  ],
  exports: [RouterModule]
})
export class AppRoutingModule { }
