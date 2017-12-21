'use strict';
/*
 * xajax Form Values test
 */
describe('Testing Form-Values', function () {
    
    // API for interacting with the page.
    var controls = {
        get formValues() {
            return xajax.forms.getFormValues('concreteId');
        }
    }; // inject the HTML fixture for the tests
    beforeEach(function () {
    
    });
    // remove the html fixture from the DOM
    afterEach(function () {
        fixture.cleanup();
    });
    
    it('No Form-Node Found by Null', function () {
        xajax.forms.getFormValues(null);
        assert.equal(xajax.forms.getFormValues(null), null);
    });
    
    it('No Form-Node Found by none existing id', function () {
        assert.equal(xajax.forms.getFormValues('iamNotExists'), null);
    });
    
    // todo check zero Values
    it('Simple Formcheck that vars will be collected to object', function () {
        fixture.load('form_regular.html');
        var vars = controls.formValues;
        var chckObject = {hiddenOne: '12', textOne: '124', dateOne: '2017-12-30', textareaOne: '<div>teststring</div>', radioOne: 'valOne', chckbx: {caseOne: 'valOne', caseTwo: 'valTwo'}};
        assert.equal(JSON.stringify(vars), JSON.stringify(chckObject));
    });
    
    it('Multiple Select', function () {
        fixture.load('form_multiple_select.html');
        var vars = controls.formValues;
        var chckObject = {hiddenOne: '12', textOne: '124', dateOne: '2017-12-30', textareaOne: '<div>teststring</div>', radioOne: 'valOne', chckbx: {caseOne: 'valOne', caseTwo: 'valTwo'}};
        assert.equal(JSON.stringify(vars), JSON.stringify(chckObject));
    });
});