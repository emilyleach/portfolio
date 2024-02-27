import datetime
import xml.etree.ElementTree as ET
file = ET.parse('TouchnetPaymentPlanSanitized.xml')
root = file.getroot()

today = datetime.datetime.now()  # grab the current day, so we can know which payments have passed
currentYear = int(today.strftime("%Y"))  # grab the current year, so we know when the payments are due


for row in root:
    totalPlanAmount = float(row.find('TOTAL_PLAN_AMOUNT').text)  # this is the amount the payment plan was agreed to
    remainingBalance = float(row.find('REMAINING_PLAN_AMOUNT').text)  # this is how much is left
    planDescription = row.find('PLAN_DESCRIPTION').text  # this is which payment plan was selected

    totalPayments = 0
    if "4 Installment" in planDescription:
        totalPayments = 4
    elif "3 Installment" in planDescription:
        totalPayments = 3
    elif "2 Installment" in planDescription:
        totalPayments = 2

    # determine when the dates are for payment plan based on XML file
    payDates = []  # this array will hold all the dates for the payment plan. It holds them as date objects
    if "Fall" in planDescription:
        payDates = [datetime.datetime(currentYear, 11, 5), datetime.datetime(currentYear, 12, 5)]
        if totalPayments == 3 or totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 10, 5))
        if totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 9, 5))

    elif "Spring" in planDescription:
        payDates = [datetime.datetime(currentYear, 3, 5), datetime.datetime(currentYear, 4, 5)]
        if totalPayments == 3 or totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 2, 5))
        if totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 1, 5))

    elif "Summer" in planDescription:
        payDates = [datetime.datetime(currentYear, 7, 5), datetime.datetime(currentYear, 8, 5)]
        if totalPayments == 3 or totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 6, 5))
        if totalPayments == 4:
            payDates.append(datetime.datetime(currentYear, 5, 5))

    # determine the amount of each payment and how many payments are left
    paymentAmount = totalPlanAmount / totalPayments
    paymentsLeft = totalPayments
    for payDate in payDates:
        if payDate < today:
            paymentsLeft -= 1
    remainingTotalLeft = paymentAmount * paymentsLeft
    # this is how much would be left on their account if they paid in full and on time

    # determine if account is past due on some payments
    if remainingTotalLeft < remainingBalance:
        # this person hasn't made their full payment amounts.
        row.find('REMAINING_PLAN_AMOUNT').text = "{:.2f}".format(remainingTotalLeft)
        # this formats the remaining total into a float with 2 decimal spaces at the end
        row.find('REMAINING_PLAN_AMOUNT').set('updated', 'yes')

file.write('updatedTouchnetPaymentPlanSanitized.xml')

# Fall
#   - 4 Installments: Sept 5th, Oct 5th, Nov 5th, Dec 5th
#   - 3 Installments: Oct 5th, Nov 5th, Dec 5th
#   - 2 Installments: Nov 5th, Dec 5th
#
# Spring
#   - 4 Installments: Jan 5th, Feb 5th, Mar 5th, Apr 5th
#   - 3 Installments: Feb 5th, Mar 5th, Apr 5th
#   - 2 Installments: Mar 5th, Apr 5th
#
# Summer
#   - 4 Installments: May 5th, June 5th, July 5th, Aug 5th
#   - 3 Installments: June 5th, July 5th, Aug 5th
#   - 2 Installments: July 5th, Aug 5th
